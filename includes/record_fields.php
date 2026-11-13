<?php
/* field => [label, input kind, column width]  (used by admin/record_edit.php) */
$top = [
  'registration_number' => ['Registration number', 'text', 6], 'visit_date' => ['Visit date & time', 'datetime-local', 6],
  'flock_size' => ['Flock size', 'number', 4], 'age' => ['Age (days / weeks)', 'text', 4],
];
return [
  'broiler' => $top + [
    'average_weight' => ['Average weight', 'text', 4], 'weight_gain' => ['Weight gain', 'text', 4], 'feed_intake' => ['Feed intake', 'text', 4],
    'mortality' => ['Mortality', 'text', 4], 'mortality_pct' => ['Mortality %', 'text', 4],
    'complaint' => ['Complaint', 'area', 12], 'clinical_signs' => ['Clinical signs', 'area', 12], 'dd' => ['DD (differential diagnosis)', 'text', 12],
    'test_request' => ['Test request', 'area', 6], 'test_report' => ['Test report', 'area', 6],
    'recommendation' => ['Recommendation', 'area', 6], 'treatment' => ['Treatment', 'area', 6], 'follow_ups' => ['Follow ups', 'area', 12]],
  'layer' => $top + [
    'average_age' => ['Average age', 'text', 4], 'egg_production' => ['Egg production', 'text', 4], 'egg_production_pct' => ['Egg production %', 'text', 4],
    'feed_intake' => ['Feed intake', 'text', 4], 'mortality' => ['Mortality', 'text', 4], 'mortality_pct' => ['Mortality %', 'text', 4],
    'complaint' => ['Complaint / visit purpose', 'area', 12], 'clinical_signs' => ['Clinical signs', 'area', 12], 'post_mortem_changes' => ['Post-mortem changes', 'area', 12],
    'dd' => ['DD (differential diagnosis)', 'text', 12], 'test_request' => ['Test request', 'area', 6], 'test_report' => ['Test report', 'area', 6],
    'recommendation' => ['Recommendation / advice', 'area', 6], 'treatment' => ['Treatment', 'area', 6], 'follow_ups' => ['Follow ups', 'area', 12]],
];
