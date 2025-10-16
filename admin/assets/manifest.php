<?php
return [
    'css' => [
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        'https://cdn.jsdelivr.net/npm/@yaireo/tagify@4.35.4/dist/tagify.css',
        'admin/assets/css/admin.css',
    ],
    'js' => [
        ['src' => 'https://cdn.jsdelivr.net/npm/@yaireo/tagify@4.35.4/dist/tagify.min.js', 'defer' => false],
        ['src' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', 'defer' => true],
        ['src' => 'admin/assets/js/admin.js', 'defer' => true],
    ],
];
