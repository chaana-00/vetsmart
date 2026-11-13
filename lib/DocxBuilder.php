<?php
/**
 * DocxBuilder - dependency-free Word (.docx) writer for VetSmart (needs the PHP "zip" extension).
 *
 * Every document gets, automatically, on EVERY page:
 *   HEADER : centred logo -> "SmartVet" title -> tagline -> blue/green colour strip
 *   FOOTER : "This document is system generated!" + copyright line + page x of y
 *
 * Public API (unchanged, so includes/docx.php keeps working):
 *   new DocxBuilder(bool $landscape = false)
 *   title(string $title, string $subtitle = '')      document title banner
 *   heading(string $text, int $level = 1)
 *   para(string $text, array $opts = [])             opts: bold, italic, color, size (half-points), align
 *   kv(array $pairs)                                 [[label, value], ...]
 *   table(array $headers, array $rows, array $widths = [], string $headFill = '1747C9')
 *   image(string $path): bool                        jpg / png / gif (webp converted when GD allows)
 *   spacer(), pageBreak()
 *   stream(string $filename)                         sends the file and exits
 */
class DocxBuilder
{
    /* ---- branding (edit here) ---- */
    public static $brandName   = ['Smart', 'Vet'];            // two-tone title in the header
    /** The logo already carries the VetSmart name; set true to also print the big text title under it. */
    public static $showTitle   = false;
    // public static $tagline     = 'Poultry health and farm visit records';
    public static $footerLine1 = 'This document is system generated!';
    public static $footerPre   = '© 2027 | VetSmart Designed & Developed by ';
    public static $footerBold  = 'DYVESTA IT';
    public static $footerPost  = ' | +94 70 228 5959';
    /** Logo shown at the top-centre of every page. First existing file wins. */
    public static $logoPath    = null;

    /* ---- theme colours (match the web app) ---- */
    const INK = '06222F'; const COBALT = '2160FF'; const COBALT_D = '1747C9';
    const FERN = '12A36B'; const FERN_D = '0B7F52'; const MINT = 'E4F6EE'; const MUTED = '5D7880'; const LINE = 'CFE0E8';

    private $body = '';
    private $landscape;
    private $contentW;
    private $media = [];       // [zipName, srcPath, ext]
    private $bodyRels = [];    // [rId, zipName]
    private $tmpFiles = [];
    private $imgCount = 0;
    private $docPrId = 100;

    public function __construct(bool $landscape = false)
    {
        $this->landscape = $landscape;
        $this->contentW = $landscape ? 14570 : 9638;   // A4 minus 2 x 1134 twip margins
    }

    /* ======================= helpers ======================= */
    private static function x($s): string
    {
        $s = (string)$s;
        if (function_exists('iconv')) { $t = @iconv('UTF-8', 'UTF-8//IGNORE', $s); if ($t !== false) $s = $t; }
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** run properties in schema order: rFonts, b, i, color, spacing, sz */
    private static function rpr(array $o): string
    {
        $r = '';
        if (!empty($o['font']))   $r .= '<w:rFonts w:ascii="' . $o['font'] . '" w:hAnsi="' . $o['font'] . '" w:cs="' . $o['font'] . '"/>';
        if (!empty($o['bold']))   $r .= '<w:b/>';
        if (!empty($o['italic'])) $r .= '<w:i/>';
        if (!empty($o['color']))  $r .= '<w:color w:val="' . $o['color'] . '"/>';
        if (!empty($o['track']))  $r .= '<w:spacing w:val="' . (int)$o['track'] . '"/>';
        if (!empty($o['size']))   $r .= '<w:sz w:val="' . (int)$o['size'] . '"/><w:szCs w:val="' . (int)$o['size'] . '"/>';
        return $r === '' ? '' : '<w:rPr>' . $r . '</w:rPr>';
    }

    private static function runs($text, array $o = []): string
    {
        $rpr = self::rpr($o);
        $out = '';
        foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $i => $line) {
            if ($i > 0) $out .= '<w:r>' . $rpr . '<w:br/></w:r>';
            $out .= '<w:r>' . $rpr . '<w:t xml:space="preserve">' . self::x($line) . '</w:t></w:r>';
        }
        return $out;
    }

    private static function p(string $inner, string $ppr = ''): string
    {
        return '<w:p>' . ($ppr === '' ? '' : '<w:pPr>' . $ppr . '</w:pPr>') . $inner . '</w:p>';
    }

    private function cell(int $w, string $inner, string $fill = '', string $extra = ''): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/>' . $extra
            . ($fill !== '' ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '')
            . '<w:vAlign w:val="center"/></w:tcPr>' . $inner . '</w:tc>';
    }

    private function dxaWidths(array $rel, int $n): array
    {
        if (count($rel) !== $n || array_sum($rel) <= 0) $rel = array_fill(0, $n, 1);
        $sum = array_sum($rel); $out = []; $used = 0;
        foreach ($rel as $i => $v) { $w = ($i === $n - 1) ? $this->contentW - $used : (int)floor($this->contentW * $v / $sum); $out[] = $w; $used += $w; }
        return $out;
    }

    private static function tblOpen(array $grid, string $borders): string
    {
        $g = ''; foreach ($grid as $w) $g .= '<w:gridCol w:w="' . $w . '"/>';
        return '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($grid) . '" w:type="dxa"/><w:tblBorders>' . $borders . '</w:tblBorders>'
            . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="70" w:type="dxa"/><w:left w:w="110" w:type="dxa"/><w:bottom w:w="70" w:type="dxa"/><w:right w:w="110" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . '<w:tblGrid>' . $g . '</w:tblGrid>';
    }
    private static function bordersAll(string $color = self::LINE, int $sz = 4): string
    {
        $b = ''; foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $s) $b .= '<w:' . $s . ' w:val="single" w:sz="' . $sz . '" w:space="0" w:color="' . $color . '"/>';
        return $b;
    }
    private static function bordersNone(): string
    {
        $b = ''; foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $s) $b .= '<w:' . $s . ' w:val="nil"/>';
        return $b;
    }

    private static function drawing(string $rid, int $cx, int $cy, int $id, string $name): string
    {
        $name = self::x($name);
        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $id . '" name="' . $name . '"/><wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
            . '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="' . $name . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    /* ======================= content API ======================= */

    /** Document title banner (dark ink block, green accent edge) */
    public function title(string $title, string $subtitle = ''): void
    {
        $w = $this->contentW;
        $inner = self::p(self::runs($title, ['bold' => true, 'color' => 'FFFFFF', 'size' => 44, 'font' => 'Calibri']), '<w:spacing w:before="60" w:after="40"/>');
        if ($subtitle !== '') $inner .= self::p(self::runs($subtitle, ['color' => '8FE3BE', 'size' => 18]), '<w:spacing w:before="0" w:after="60"/>');
        $edge = '<w:tcBorders><w:left w:val="single" w:sz="48" w:space="0" w:color="' . self::FERN . '"/></w:tcBorders>';
        $this->body .= self::tblOpen([$w], self::bordersNone())
            . '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $this->cell($w, $inner, self::INK, $edge) . '</w:tr></w:tbl>'
            . self::p('', '<w:spacing w:before="0" w:after="120"/>');
    }

    public function heading(string $text, int $level = 1): void
    {
        $style = $level <= 1 ? 'Heading1' : 'Heading2';
        $this->body .= self::p(self::runs($text), '<w:pStyle w:val="' . $style . '"/>');
    }

    public function para(string $text, array $opts = []): void
    {
        $o = $opts + [];
        $align = $o['align'] ?? 'left';
        $this->body .= self::p(self::runs($text, $o), '<w:spacing w:before="0" w:after="80"/><w:jc w:val="' . $align . '"/>');
    }

    public function spacer(): void { $this->body .= self::p('', '<w:spacing w:before="0" w:after="120"/>'); }
    public function pageBreak(): void { $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'; }

    /** Two-column label / value table */
    public function kv(array $pairs): void
    {
        $w1 = (int)round($this->contentW * 0.28); $w2 = $this->contentW - $w1;
        $t = self::tblOpen([$w1, $w2], self::bordersAll());
        foreach ($pairs as $pr) {
            $label = $pr[0] ?? ''; $val = $pr[1] ?? '';
            if ($val === null || $val === '') $val = '—';
            $t .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>'
                . $this->cell($w1, self::p(self::runs($label, ['bold' => true, 'color' => self::INK, 'size' => 20]), '<w:spacing w:before="0" w:after="0"/>'), self::MINT)
                . $this->cell($w2, self::p(self::runs($val, ['size' => 20]), '<w:spacing w:before="0" w:after="0"/>'))
                . '</w:tr>';
        }
        $this->body .= $t . '</w:tbl>' . self::p('', '<w:spacing w:before="0" w:after="100"/>');
    }

    /** Data table: coloured header row, zebra rows. $widths are relative numbers. */
    public function table(array $headers, array $rows, array $widths = [], string $headFill = self::COBALT_D): void
    {
        $n = count($headers);
        $grid = $this->dxaWidths($widths, $n);
        $sz = $n > 6 ? 16 : ($n > 4 ? 18 : 20);
        $t = self::tblOpen($grid, self::bordersAll());
        $t .= '<w:tr><w:trPr><w:cantSplit/><w:tblHeader/></w:trPr>';
        foreach ($headers as $i => $h) $t .= $this->cell($grid[$i], self::p(self::runs($h, ['bold' => true, 'color' => 'FFFFFF', 'size' => $sz]), '<w:spacing w:before="0" w:after="0"/>'), $headFill);
        $t .= '</w:tr>';
        if (!$rows) {
            $t .= '<w:tr>' . $this->cell($grid[0], self::p(self::runs('No records', ['italic' => true, 'color' => self::MUTED, 'size' => $sz]), '<w:spacing w:before="0" w:after="0"/>'), '', '<w:gridSpan w:val="' . $n . '"/>') . '</w:tr>';
        }
        foreach (array_values($rows) as $ri => $row) {
            $fill = $ri % 2 ? 'F3FAF7' : '';
            $t .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            for ($i = 0; $i < $n; $i++) {
                $v = $row[$i] ?? '';
                $t .= $this->cell($grid[$i], self::p(self::runs($v === null ? '' : $v, ['size' => $sz]), '<w:spacing w:before="0" w:after="0"/>'), $fill);
            }
            $t .= '</w:tr>';
        }
        $this->body .= $t . '</w:tbl>' . self::p('', '<w:spacing w:before="0" w:after="100"/>');
    }

    /** Embed an image (centred, scaled to fit). Returns false when it cannot be used. */
    public function image(string $path): bool
    {
        if (!is_file($path) || !($info = @getimagesize($path))) return false;
        $mime = $info['mime'] ?? '';
        $src = $path;
        if ($mime === 'image/webp') {
            if (!function_exists('imagecreatefromwebp')) return false;
            $im = @imagecreatefromwebp($path); if (!$im) return false;
            $src = tempnam(sys_get_temp_dir(), 'vsw'); imagejpeg($im, $src, 88); imagedestroy($im);
            $this->tmpFiles[] = $src; $mime = 'image/jpeg';
        }
        $ext = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/gif' => 'gif'][$mime] ?? null;
        if (!$ext) return false;
        [$wpx, $hpx] = $info; if ($wpx < 1 || $hpx < 1) return false;
        $maxW = ($this->landscape ? 8.6 : 5.9); $maxH = ($this->landscape ? 3.6 : 4.0);   // inches
        $wi = $wpx / 96; $hi = $hpx / 96;
        $s = min(1, $maxW / $wi, $maxH / $hi);
        $cx = (int)round($wi * $s * 914400); $cy = (int)round($hi * $s * 914400);
        $this->imgCount++;
        $zip = 'media/image' . $this->imgCount . '.' . $ext; $rid = 'rId' . (20 + $this->imgCount);
        $this->media[] = [$zip, $src]; $this->bodyRels[] = [$rid, $zip];
        $this->body .= self::p(self::drawing($rid, $cx, $cy, ++$this->docPrId, basename($path)), '<w:keepNext/><w:spacing w:before="60" w:after="40"/><w:jc w:val="center"/>');
        return true;
    }

    /* ======================= header / footer ======================= */

    private function findLogo(): ?string
    {
        $c = [];
        if (self::$logoPath) $c[] = self::$logoPath;
        $root = dirname(__DIR__);
        foreach (['docx_logo.png', 'logo.png', 'logo.jpg', 'vetsmart-logo.png'] as $f) { $c[] = "$root/assets/img/$f"; $c[] = "$root/assets/images/$f"; $c[] = "$root/assets/$f"; }
        foreach ($c as $p) if (is_file($p) && @getimagesize($p)) return $p;
        return null;
    }

    private function headerXml(?array $logo): string
    {
        $x = '';
        if ($logo) {
            [$wpx, $hpx] = $logo['size']; $wi = 2.7; $hi = $wi * $hpx / $wpx;   // 2.7in wide, centred
            $x .= self::p(self::drawing('rId1', (int)round($wi * 914400), (int)round($hi * 914400), 1, 'Logo'), '<w:spacing w:before="0" w:after="60"/><w:jc w:val="center"/>');
        }
        if (self::$showTitle) $x .= self::p(
            self::runs(self::$brandName[0], ['bold' => true, 'color' => self::COBALT, 'size' => 56, 'track' => 20, 'font' => 'Calibri'])
            . self::runs(self::$brandName[1], ['bold' => true, 'color' => self::FERN, 'size' => 56, 'track' => 20, 'font' => 'Calibri']),
            '<w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/>');
        // $x .= self::p(self::runs(self::$tagline, ['italic' => true, 'color' => self::MUTED, 'size' => 18]), '<w:spacing w:before="0" w:after="80"/><w:jc w:val="center"/>');
        // blue -> green colour strip
        $a = 3200; $b = 10000 - $a;
        $tcp = function ($w, $fill) { return '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="pct"/><w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/></w:tcPr>' . self::p('', '<w:spacing w:before="0" w:after="0" w:line="20" w:lineRule="exact"/>') . '</w:tc>'; };
        $x .= '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>' . self::bordersNone() . '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="' . (int)($this->contentW * 0.32) . '"/><w:gridCol w:w="' . ($this->contentW - (int)($this->contentW * 0.32)) . '"/></w:tblGrid>'
            . '<w:tr><w:trPr><w:trHeight w:val="90" w:hRule="exact"/></w:trPr>' . $tcp(1600, self::COBALT) . $tcp(3400, self::FERN) . '</w:tr></w:tbl>';
        $x .= self::p('', '<w:spacing w:before="0" w:after="0" w:line="20" w:lineRule="exact"/>');
        return $x;
    }

    private function footerXml(): string
    {
        $top = '<w:pBdr><w:top w:val="single" w:sz="12" w:space="6" w:color="' . self::FERN . '"/></w:pBdr>';
        $x = self::p(self::runs(self::$footerLine1, ['italic' => true, 'bold' => true, 'color' => self::INK, 'size' => 18]), $top . '<w:spacing w:before="0" w:after="20"/><w:jc w:val="center"/>');
        $x .= self::p(
            self::runs(self::$footerPre, ['color' => self::MUTED, 'size' => 17])
            . self::runs(self::$footerBold, ['bold' => true, 'color' => self::COBALT_D, 'size' => 17])
            . self::runs(self::$footerPost, ['color' => self::MUTED, 'size' => 17]),
            '<w:spacing w:before="0" w:after="20"/><w:jc w:val="center"/>');
        $rp = '<w:rPr><w:color w:val="' . self::MUTED . '"/><w:sz w:val="16"/></w:rPr>';
        $fld = function ($instr) use ($rp) { return '<w:r>' . $rp . '<w:fldChar w:fldCharType="begin"/></w:r><w:r>' . $rp . '<w:instrText xml:space="preserve"> ' . $instr . ' </w:instrText></w:r><w:r>' . $rp . '<w:fldChar w:fldCharType="separate"/></w:r><w:r>' . $rp . '<w:t>1</w:t></w:r><w:r>' . $rp . '<w:fldChar w:fldCharType="end"/></w:r>'; };
        $txt = function ($t) use ($rp) { return '<w:r>' . $rp . '<w:t xml:space="preserve">' . $t . '</w:t></w:r>'; };
        $x .= self::p($txt('Page ') . $fld('PAGE') . $txt(' of ') . $fld('NUMPAGES'), '<w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/>');
        return $x;
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri" w:eastAsia="Calibri"/><w:color w:val="10303F"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:lang w:val="en-GB"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="3" w:color="' . self::FERN . '"/></w:pBdr><w:spacing w:before="280" w:after="140"/><w:outlineLvl w:val="0"/></w:pPr>'
            . '<w:rPr><w:b/><w:color w:val="' . self::COBALT_D . '"/><w:sz w:val="34"/><w:szCs w:val="34"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:spacing w:before="220" w:after="100"/><w:outlineLvl w:val="1"/></w:pPr>'
            . '<w:rPr><w:b/><w:color w:val="' . self::FERN_D . '"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>'
            . '</w:styles>';
    }

    /* ======================= build & send ======================= */

    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

    public function build(string $outFile): void
    {
        $zip = new ZipArchive();
        if ($zip->open($outFile, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) throw new RuntimeException('Cannot create docx file');

        // header logo
        $logo = null;
        if ($p = $this->findLogo()) { $i = getimagesize($p); $ext = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/gif' => 'gif'][$i['mime']] ?? null; if ($ext) $logo = ['path' => $p, 'size' => [$i[0], $i[1]], 'zip' => 'media/logo.' . $ext]; }

        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $zip->addFromString('[Content_Types].xml', $head
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="jpg" ContentType="image/jpeg"/><Default Extension="gif" ContentType="image/gif"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');

        $R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $rels = '<Relationship Id="rId1" Type="' . $R . '/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId2" Type="' . $R . '/header" Target="header1.xml"/>'
            . '<Relationship Id="rId3" Type="' . $R . '/footer" Target="footer1.xml"/>';
        foreach ($this->bodyRels as [$rid, $target]) $rels .= '<Relationship Id="' . $rid . '" Type="' . $R . '/image" Target="' . $target . '"/>';
        $zip->addFromString('word/_rels/document.xml.rels', $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');

        $pg = $this->landscape ? '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>' : '<w:pgSz w:w="11906" w:h="16838"/>';
        $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rId2"/><w:footerReference w:type="default" r:id="rId3"/>' . $pg
            . '<w:pgMar w:top="2650" w:right="1134" w:bottom="1500" w:left="1134" w:header="360" w:footer="420" w:gutter="0"/></w:sectPr>';
        $zip->addFromString('word/document.xml', $head . '<w:document ' . self::NS . '><w:body>' . $this->body . $sect . '</w:body></w:document>');
        $zip->addFromString('word/styles.xml', self::styles());
        $zip->addFromString('word/header1.xml', $head . '<w:hdr ' . self::NS . '>' . $this->headerXml($logo) . '</w:hdr>');
        $zip->addFromString('word/footer1.xml', $head . '<w:ftr ' . self::NS . '>' . $this->footerXml() . '</w:ftr>');
        $zip->addFromString('word/_rels/header1.xml.rels', $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . ($logo ? '<Relationship Id="rId1" Type="' . $R . '/image" Target="' . $logo['zip'] . '"/>' : '') . '</Relationships>');

        if ($logo) $zip->addFile($logo['path'], 'word/' . $logo['zip']);
        foreach ($this->media as [$name, $src]) $zip->addFile($src, 'word/' . $name);
        $zip->close();
        foreach ($this->tmpFiles as $t) @unlink($t);
    }

    public function stream(string $filename): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vsd');
        $this->build($tmp);
        while (ob_get_level()) ob_end_clean();
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}