<?php

use App\Core\Auth;
use App\Core\Lang;
use App\Core\Csrf;

$currentLang = Lang::getLocale();
$pageTitle = $pageTitle ?? t('app_name');
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(Csrf::getToken()) ?>">
    <title><?= e($pageTitle) ?> - Osuthuru</title>
    <!-- Bootstrap 5.3 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/assets/icons/maskable-192.png">
    <meta name="theme-color" content="#04302b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Osuthuru">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Noto+Sans+Sinhala:wght@400;500;600;700;800&display=swap">
    <!-- Custom Theme -->
    <link rel="stylesheet" href="/assets/css/style.css?v=15">
</head>
<body>
<a class="skip-link" href="#content"><?= e(t('skip_to_content')) ?></a>

<header>
    <nav class="navbar navbar-expand-xl navbar-dark navbar-custom fixed-top">
        <div class="container-fluid px-3 px-xxl-5">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <img src="/assets/images/logo.png" alt="Osuthuru Logo" width="45" height="45" class="rounded-circle">
                <div class="d-flex flex-column">
                    <span class="fw-bold fs-5 lh-1"><?= e(t('app_name')) ?></span>
                    <small class="text-white-50 fs-8 lh-1 d-none d-sm-inline d-xl-none"><?= e(t('tagline')) ?></small>
                </div>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-label="<?= e(t('nav_menu')) ?>">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Nav Items -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="bi bi-house-door nav-icon me-1"></i><?= e(t('nav_home')) ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/products"><i class="bi bi-grid nav-icon me-1"></i><?= e(t('nav_products')) ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dictionary"><i class="bi bi-journal-medical nav-icon me-1"></i><?= e(t('nav_dictionary')) ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/quiz"><i class="bi bi-clipboard2-pulse nav-icon me-1"></i><?= e(t('nav_quiz')) ?></a>
                    </li>
                </ul>

                <!-- Search Bar with Autocomplete & Voice Search -->
                <form class="d-flex position-relative me-xl-3 my-2 my-xl-0 nav-search" action="/search" method="GET">
                    <div class="input-group input-group-sm">
                        <input type="text" name="q" id="globalSearchInput" class="form-control"
                               placeholder="<?= e(t('search_placeholder')) ?>" aria-label="<?= e(t('search_placeholder')) ?>"
                               value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
                        <button class="btn btn-outline-light border-start-0" type="button" id="voiceSearchBtn" title="<?= e(t('voice_search')) ?>" aria-label="<?= e(t('voice_search')) ?>" data-listening="<?= e(t('voice_listening')) ?>">
                            <i class="bi bi-mic-fill" id="voiceMicIcon"></i>
                        </button>
                        <button class="btn btn-light text-success fw-bold" type="submit" aria-label="<?= e(t('search_button')) ?>">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <!-- Autocomplete dropdown container -->
                    <div id="searchSuggestBox"
                         data-concern="<?= e(t('suggest_concern')) ?>"
                         data-concerns="<?= e(t('suggest_concerns')) ?>"
                         data-herbs="<?= e(t('suggest_herbs')) ?>"
                         data-products="<?= e(t('nav_products')) ?>"
                         class="dropdown-menu w-100 shadow border-0 mt-1 d-none" style="position: absolute; top: 100%; left: 0; z-index: 1050; max-height: 380px; overflow-y: auto;">
                    </div>
                </form>

                <!-- Language Switcher & User Profile -->
                <div class="d-flex align-items-center gap-2">
                    <!-- Language selector form -->
                    <div class="btn-group btn-group-sm" role="group">
                        <form action="/lang" method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="lang" value="en">
                            <button type="submit" class="btn btn-sm <?= $currentLang === 'en' ? 'btn-light text-success fw-bold' : 'btn-outline-light' ?>">EN</button>
                        </form>
                        <form action="/lang" method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="lang" value="si">
                            <button type="submit" class="btn btn-sm <?= $currentLang === 'si' ? 'btn-light text-success fw-bold' : 'btn-outline-light' ?>">සිං</button>
                        </form>
                    </div>

                    <!-- Cart -->
                    <a href="/cart" class="btn btn-outline-light btn-sm position-relative" title="<?= e(t('nav_cart')) ?>" aria-label="<?= e(t('nav_cart')) ?>">
                        <i class="bi bi-cart3"></i>
                    </a>

                    <!-- Auth Links -->
                    <?php if (Auth::check()): ?>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm dropdown-toggle nav-user" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?= e(Auth::user()['name'] ?? t('user_fallback')) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i><?= e(t('profile_title')) ?></a></li>
                                <li><a class="dropdown-item" href="/orders"><i class="bi bi-receipt me-2"></i><?= e(t('my_orders')) ?></a></li>
                                <li><a class="dropdown-item" href="/profile/categories"><i class="bi bi-stars me-2"></i><?= e(t('interests_title')) ?></a></li>
                                <li><a class="dropdown-item" href="/wishlist"><i class="bi bi-heart me-2"></i><?= e(t('wishlist')) ?></a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="/logout" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i><?= e(t('nav_logout')) ?></button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php elseif (Auth::isAdmin()): ?>
                        <!-- Icon only where the bar is narrowest (xl), so it never wraps -->
                        <a href="/admin/dashboard" class="btn btn-light btn-sm text-nowrap" title="<?= e(t('admin_dashboard')) ?>" aria-label="<?= e(t('admin_dashboard')) ?>"><i class="bi bi-speedometer2"></i><span class="ms-1 d-xl-none d-xxl-inline"><?= e(t('admin_dashboard')) ?></span></a>
                    <?php else: ?>
                        <a href="/login" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i><?= e(t('nav_login')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="content" class="container py-4" tabindex="-1">
