<?php
/**
 * @var string $title   Seitentitel (wird escaped)
 * @var string $content Bereits gerendertes Inhalts-HTML
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Chat') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💬</text></svg>">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?= $content ?? '' ?>
</body>
</html>
