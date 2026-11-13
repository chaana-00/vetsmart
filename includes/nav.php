<?php
/* Sidebar definition. Each group: [label, [ [key, icon, label, href], ... ]] */
function nav_items(): array {
    if (is_admin()) {
        return [
            ['Overview', [
                ['dashboard', 'bi-grid-1x2-fill', 'Dashboard', 'admin/dashboard.php'],
                ['notifications', 'bi-bell-fill', 'Activity & alerts', 'user/notifications.php'],
            ]],
            ['People', [
                ['create_user', 'bi-person-plus-fill', 'Create user', 'admin/create_user.php'],
                ['manage_users', 'bi-people-fill', 'Manage users', 'admin/manage_users.php'],
            ]],
            ['Field data', [
                ['farms', 'bi-house-heart-fill', 'Farms', 'user/farms.php'],
                ['batches', 'bi-collection-fill', 'Cages & batches', 'user/batches.php'],
                ['records', 'bi-clipboard2-pulse-fill', 'Visit records', 'user/records.php'],
                ['prescriptions', 'bi-capsule-pill', 'Prescriptions', 'user/prescriptions.php'],
            ]],
        ];
    }
    return [
        ['Overview', [
            ['dashboard', 'bi-grid-1x2-fill', 'Dashboard', 'user/dashboard.php'],
            ['notifications', 'bi-bell-fill', 'History & alerts', 'user/notifications.php'],
        ]],
        ['Farms', [
            ['farm_register', 'bi-plus-circle-fill', 'Register farm', 'user/farm_register.php'],
            ['farms', 'bi-house-heart-fill', 'Farm records', 'user/farms.php'],
        ]],
        ['Cages & batches', [
            ['batch_add', 'bi-egg-fill', 'Add cage / batch', 'user/batch_add.php'],
            ['batches', 'bi-collection-fill', 'View batches', 'user/batches.php'],
        ]],
        ['Visits', [
            ['add_records', 'bi-journal-plus', 'New visit record', 'user/add_records.php'],
            ['records', 'bi-clipboard2-pulse-fill', 'Visit records', 'user/records.php'],
            ['prescription', 'bi-capsule', 'Create prescription', 'user/prescription.php'],
            ['prescriptions', 'bi-file-earmark-medical-fill', 'Prescriptions', 'user/prescriptions.php'],
        ]],
    ];
}
