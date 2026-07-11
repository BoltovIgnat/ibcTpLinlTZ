<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $APPLICATION->ShowHead(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_TEMPLATE_PATH ?>/styles.css?v=1.2.1">
    <title><?php $APPLICATION->ShowTitle(); ?></title>
</head>
<body class="tplink-body">
<div id="panel"><?php $APPLICATION->ShowPanel(); ?></div>
