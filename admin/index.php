<?php

declare(strict_types=1);

session_start();

require __DIR__ . '/../lib/DataStore.php';

$config = include __DIR__ . '/../config/commands-config.php';
$dataPath = $config['paths']['data'] ?? (__DIR__ . '/../config/commands-data.json');
$adminConfig = $config['admin'] ?? [];
$sessionKey = $adminConfig['session_key'] ?? 'moonland_commands_admin';
$username = (string) ($adminConfig['username'] ?? 'admin');
$password = (string) ($adminConfig['password'] ?? 'change-me');

$isAuthenticated = !empty($_SESSION[$sessionKey] ?? false);
$errors = [];

function normaliseLanguageCode(string $code): string
{
	$normalised = strtolower(trim($code));
	return preg_match('/^[a-z0-9_-]{2,10}$/', $normalised) ? $normalised : '';
}

function extractLegacyTranslation(array $data): array
{
	return [
		'meta' => is_array($data['meta'] ?? null) ? $data['meta'] : [],
		'hero' => is_array($data['hero'] ?? null) ? $data['hero'] : [],
		'infoStrip' => is_array($data['infoStrip'] ?? null) ? $data['infoStrip'] : [],
		'guide' => is_array($data['guide'] ?? null) ? $data['guide'] : [],
		'catalog' => is_array($data['catalog'] ?? null) ? $data['catalog'] : [],
		'tips' => is_array($data['tips'] ?? null) ? $data['tips'] : [],
		'faq' => is_array($data['faq'] ?? null) ? $data['faq'] : [],
		'footer' => is_array($data['footer'] ?? null) ? $data['footer'] : [],
	];
}

function prepareMultilingualPayload(array $data): array
{
	$languages = [];
	if (isset($data['languages']) && is_array($data['languages'])) {
		foreach ($data['languages'] as $code) {
			$normalised = normaliseLanguageCode((string) $code);
			if ($normalised !== '') {
				$languages[$normalised] = $normalised;
			}
		}
	}

	$translations = [];
	if (isset($data['translations']) && is_array($data['translations'])) {
		foreach ($data['translations'] as $code => $translation) {
			$normalised = normaliseLanguageCode((string) $code);
			if ($normalised === '') {
				continue;
			}
			$languages[$normalised] = $normalised;
			$translations[$normalised] = is_array($translation) ? $translation : [];
		}
	}

	if (!$translations) {
		$defaultLanguage = normaliseLanguageCode((string) ($data['defaultLanguage'] ?? 'ro')) ?: 'ro';
		$languages[$defaultLanguage] = $defaultLanguage;
		$translations[$defaultLanguage] = extractLegacyTranslation($data);
	}

	$languages = array_values($languages);
	if (!$languages) {
		$languages = ['ro'];
	}

	$defaultLanguage = normaliseLanguageCode((string) ($data['defaultLanguage'] ?? ''));
	if ($defaultLanguage === '' || !in_array($defaultLanguage, $languages, true)) {
		$defaultLanguage = $languages[0];
	}

	foreach ($languages as $code) {
		if (!isset($translations[$code])) {
			$translations[$code] = extractLegacyTranslation([]);
		}
	}

	return [
		'languages' => $languages,
		'defaultLanguage' => $defaultLanguage,
		'translations' => $translations,
	];
}

function countryCodeToFlagEmoji(string $countryCode): string
{
	$code = strtoupper(trim($countryCode));
	if (!preg_match('/^[A-Z]{2}$/', $code)) {
		return '';
	}
	$first = 0x1F1E6 + (ord($code[0]) - 65);
	$second = 0x1F1E6 + (ord($code[1]) - 65);
	$firstSymbol = mb_convert_encoding('&#' . $first . ';', 'UTF-8', 'HTML-ENTITIES');
	$secondSymbol = mb_convert_encoding('&#' . $second . ';', 'UTF-8', 'HTML-ENTITIES');
	return $firstSymbol . $secondSymbol;
}

function loadLanguageLabels(string $path): array
{
	if (!is_file($path)) {
		return [];
	}
	$contents = @file_get_contents($path);
	if ($contents === false) {
		return [];
	}
	$data = json_decode($contents, true);
	if (!is_array($data)) {
		return [];
	}
	$labels = [];
	foreach ($data as $code => $value) {
		$normalised = normaliseLanguageCode((string) $code);
		if ($normalised === '') {
			continue;
		}
		$label = '';
		$countryCode = '';
		if (is_array($value)) {
			$label = (string) ($value['label'] ?? $value['name'] ?? '');
			$countryCode = strtoupper(trim((string) ($value['countryCode'] ?? '')));
		} else {
			$label = (string) $value;
		}
		if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode)) {
			$countryCode = '';
		}
		$flagEmoji = $countryCode !== '' ? countryCodeToFlagEmoji($countryCode) : '';
		$labels[$normalised] = [
			'label' => trim($label),
			'countryCode' => $countryCode,
			'flagEmoji' => $flagEmoji,
			'flagIconClass' => $countryCode !== '' ? 'fi fi-' . strtolower($countryCode) : '',
		];
	}
	return $labels;
}

function renderLoginForm(array $errors): void
{
?>
	<!DOCTYPE html>
	<html lang="ro">

	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Panou admin MoonLand</title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-CFj0ZLUT0mAEo9mS3Rf0Iy3Jhk6bM8m3n7ivR0KcXsthHZXPCE6nmE4v6p6w8QLX" crossorigin="anonymous">
		<style>
			:root {
				--ml-indigo: #2d0f49;
				--ml-indigo-dark: #1b092f;
				--ml-accent: #df9c70;
				--ml-accent-dark: #b97a4d;
				--ml-surface: rgba(47, 22, 74, 0.94);
			}

			body {
				font-family: "Poppins", "Segoe UI", Arial, sans-serif;
				background: linear-gradient(180deg, rgba(45, 15, 73, 0.96), rgba(17, 6, 28, 0.92)),
					radial-gradient(circle at 20% 5%, rgba(223, 156, 112, 0.2), transparent 55%),
					rgba(17, 6, 28, 1);
				color: #f8f8ff;
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 4rem 1.5rem;
			}

			.auth-wrapper {
				width: 100%;
				max-width: 460px;
				margin: 0 auto;
			}

			.card {
				background: var(--ml-surface);
				border-radius: 24px;
				box-shadow: 0 24px 70px rgba(11, 3, 22, 0.5);
				border: 1px solid rgba(223, 156, 112, 0.25);
			}

			.card-body {
				color: rgba(252, 250, 255, 0.92);
				padding: 2.25rem 2rem;
			}

			.auth-form {
				display: flex;
				flex-direction: column;
				gap: 0.85rem;
				margin-top: 1.5rem;
			}

			.auth-form>div {
				display: flex;
				flex-direction: column;
			}

			.form-label {
				font-weight: 500;
				color: rgba(248, 248, 255, 0.82);
				margin-bottom: 0.35rem;
			}

			.form-control {
				background-color: rgba(28, 16, 48, 0.85);
				border-color: rgba(223, 156, 112, 0.28);
				color: #fdf2ff;
				border-radius: 12px;
				height: 42px;
				padding: 0 1rem;
			}

			.form-control:focus {
				background-color: rgba(32, 18, 55, 0.92);
				border-color: rgba(223, 156, 112, 0.55);
				box-shadow: 0 0 0 0.25rem rgba(223, 156, 112, 0.25);
				color: #fff;
			}

			.form-control::placeholder {
				color: rgba(248, 248, 255, 0.35);
			}

			.btn-primary {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 0.4rem;
				background-color: var(--ml-accent);
				border-color: var(--ml-accent);
				color: #221024;
				font-weight: 600;
				border-radius: 12px;
				height: 44px;
			}

			.btn-primary:hover,
			.btn-primary:focus {
				background-color: var(--ml-accent-dark);
				border-color: var(--ml-accent-dark);
				color: #1a0d1c;
			}

			.text-muted,
			.text-secondary,
			.form-text {
				color: rgba(252, 250, 255, 0.68) !important;
			}

			.alert {
				border-radius: 12px;
				background: rgba(255, 77, 109, 0.14);
				border: 1px solid rgba(255, 77, 109, 0.25);
				color: #ffd6de;
				font-weight: 500;
				margin-bottom: 1.5rem;
			}
		</style>
	</head>

	<body>
		<div class="auth-wrapper">
			<div class="card border-0 shadow-lg">
				<div class="card-body">
					<h1 class="h4 mb-1 text-center">Autentificare admin</h1>
					<p class="text-muted text-center mb-4">Conecteaza-te pentru a gestiona continutul MoonLand.</p>
					<?php if ($errors): ?>
						<div class="alert" role="alert">
							<?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?>
						</div>
					<?php endif; ?>
					<form method="post" class="auth-form">
						<div>
							<label class="form-label" for="user">Username</label>
							<input class="form-control" type="text" id="user" name="user" autocomplete="username" placeholder="Introdu username-ul" required>
						</div>
						<div>
							<label class="form-label" for="pass">Parola</label>
							<input class="form-control" type="password" id="pass" name="pass" autocomplete="current-password" placeholder="Scrie parola" required>
						</div>
						<button class="btn btn-primary" type="submit" name="login" value="1">Conecteaza-te</button>
					</form>
					<!--<p class="text-muted mt-4 mb-0 small text-center">Parola implicita este "admin". Actualizeaz-o din <code>config/commands-config.php</code>.</p>-->
				</div>
			</div>
		</div>
	</body>

	</html>
<?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
	$submittedUser = trim((string) ($_POST['user'] ?? ''));
	$submittedPass = (string) ($_POST['pass'] ?? '');
	if (hash_equals($username, $submittedUser) && hash_equals($password, $submittedPass)) {
		$_SESSION[$sessionKey] = true;
		$_SESSION[$sessionKey . '_csrf'] = bin2hex(random_bytes(16));
		header('Location: index.php');
		exit;
	}
	$errors[] = 'Credentiale invalide.';
}

if (!$isAuthenticated) {
	renderLoginForm($errors);
	exit;
}

$languageLabelsPath = $config['paths']['languageLabels'] ?? (__DIR__ . '/../config/languages.json');
$languageLabels = loadLanguageLabels($languageLabelsPath);

$dataStore = new DataStore($dataPath);
$data = prepareMultilingualPayload($dataStore->read());
$initialPayload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($initialPayload === false) {
	$initialPayload = '{}';
}

$languageLabelsJson = json_encode($languageLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($languageLabelsJson === false) {
	$languageLabelsJson = '{}';
}

$csrfToken = $_SESSION[$sessionKey . '_csrf'] ?? bin2hex(random_bytes(16));
$_SESSION[$sessionKey . '_csrf'] = $csrfToken;
$showPasswordWarning = $password === 'change-me';
?>
<!DOCTYPE html>
<html lang="ro">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Editor continut comenzi</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
	<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.8/dist/cdn.min.js"></script>
	<style>
		:root {
			--ml-indigo: #2d0f49;
			--ml-indigo-dark: #1b092f;
			--ml-accent: #df9c70;
			--ml-accent-dark: #b97a4d;
			--ml-surface: rgba(47, 22, 74, 0.94);
		}

		body {
			font-family: "Poppins", "Segoe UI", Arial, sans-serif;
			background: linear-gradient(180deg, rgba(45, 15, 73, 0.96), rgba(17, 6, 28, 0.92)),
				radial-gradient(circle at 20% 5%, rgba(223, 156, 112, 0.2), transparent 55%),
				rgba(17, 6, 28, 1);
			color: #f8f8ff;
		}

		::selection {
			background: rgba(223, 156, 112, 0.65);
			color: #130821;
		}

		.text-muted,
		.text-secondary,
		.form-text {
			color: rgba(248, 248, 255, 0.65) !important;
		}

		h1,
		h2,
		h3,
		h4,
		h5,
		h6 {
			color: #ffffff;
		}

		p,
		label,
		.form-label,
		.text-light {
			color: rgba(252, 250, 255, 0.9) !important;
		}

		.navbar {
			background: rgba(17, 6, 28, 0.85);
			border-bottom: 1px solid rgba(223, 156, 112, 0.25);
		}

		.navbar-dark .navbar-brand,
		.navbar-dark .btn,
		.navbar-dark .badge {
			color: rgba(252, 250, 255, 0.92) !important;
		}

		.card-dark {
			background: var(--ml-surface);
			border: 1px solid rgba(223, 156, 112, 0.2);
			border-radius: 24px;
			box-shadow: 0 24px 70px rgba(11, 3, 22, 0.5);
			color: rgba(252, 250, 255, 0.92);
		}

		.card-dark .border-secondary {
			border-color: rgba(223, 156, 112, 0.25) !important;
		}

		.form-control,
		.form-select,
		textarea {
			background-color: rgba(28, 16, 48, 0.85);
			border-color: rgba(223, 156, 112, 0.28);
			color: #fdf2ff;
		}

		.form-control:focus,
		.form-select:focus,
		textarea:focus {
			background-color: rgba(32, 18, 55, 0.92);
			border-color: rgba(223, 156, 112, 0.55);
			box-shadow: 0 0 0 0.25rem rgba(223, 156, 112, 0.25);
			color: #fff;
		}

		.form-control::placeholder,
		textarea::placeholder {
			color: rgba(248, 248, 255, 0.45);
		}

		label {
			font-weight: 500;
		}

		.badge-pill {
			border-radius: 999px;
		}

		.badge.bg-warning {
			color: #221024;
		}

		.alert-warning {
			background-color: rgba(223, 156, 112, 0.18);
			border-color: rgba(223, 156, 112, 0.4);
			color: #ffe9d8;
		}

		.accordion-item {
			background-color: transparent;
			border: 1px solid rgba(223, 156, 112, 0.18);
			border-radius: 18px;
			overflow: hidden;
		}

		.accordion-button {
			background-color: rgba(18, 7, 30, 0.92);
			color: rgba(248, 248, 255, 0.88);
		}

		.accordion-button:not(.collapsed) {
			background-color: rgba(223, 156, 112, 0.16);
			color: #fff;
		}

		.accordion-button:focus {
			box-shadow: 0 0 0 0.25rem rgba(223, 156, 112, 0.25);
		}

		.accordion-button::after {
			filter: invert(0.8);
		}

		.accordion-body {
			background-color: rgba(28, 16, 48, 0.88);
			color: rgba(252, 250, 255, 0.88);
		}

		.list-group-item {
			background-color: rgba(24, 12, 40, 0.85);
			border-color: rgba(223, 156, 112, 0.18);
			color: rgba(252, 250, 255, 0.88);
		}

		.list-group-item .form-control {
			background-color: rgba(30, 18, 50, 0.88);
		}

		.list-group-item .btn-outline-danger {
			color: #ffb6c1;
			border-color: rgba(255, 182, 193, 0.5);
		}

		.list-group-item .btn-outline-danger:hover {
			background-color: rgba(255, 182, 193, 0.12);
		}

		.btn-outline-light {
			color: rgba(252, 250, 255, 0.88);
			border-color: rgba(252, 250, 255, 0.35);
		}

		.btn-outline-light:hover {
			background-color: rgba(252, 250, 255, 0.12);
			color: #fff;
		}

		.btn-outline-secondary {
			color: rgba(252, 250, 255, 0.85);
			border-color: rgba(223, 156, 112, 0.35);
		}

		.btn-outline-secondary:hover {
			background-color: rgba(223, 156, 112, 0.18);
			color: #fff;
		}

		.btn-warning {
			background-color: var(--ml-accent);
			border-color: var(--ml-accent);
			color: #221024;
			font-weight: 600;
		}

		.btn-warning:hover,
		.btn-warning:focus {
			background-color: var(--ml-accent-dark);
			border-color: var(--ml-accent-dark);
			color: #160b1d;
		}

		.admin-toolbar {
			background: rgba(22, 9, 35, 0.88);
			border-bottom: 1px solid rgba(223, 156, 112, 0.18);
			backdrop-filter: blur(12px);
			z-index: 1020;
		}

		.toast.show {
			background: rgba(52, 214, 153, 0.16);
			border: 1px solid rgba(52, 214, 153, 0.45);
			color: #d1ffef;
		}
	</style>
</head>

<body x-data="commandEditor()" x-init="init()">
	<nav class="navbar navbar-expand-lg navbar-dark border-bottom border-secondary border-opacity-25">
		<div class="container-fluid">
			<span class="navbar-brand">MoonLand Admin</span>
			<div class="d-flex flex-wrap align-items-center gap-3">
				<div class="d-flex align-items-center gap-2">
					<label class="form-label text-muted mb-0 small">Limba</label>
					<select class="form-select form-select-sm text-light border-secondary" x-model="activeLanguage" @change="onLanguageChange($event)">
						<template x-for="code in languages" :key="code">
							<option :value="code" x-text="languageLabel(code)"></option>
						</template>
					</select>
					<button class="btn btn-outline-light btn-sm" type="button" @click="addLanguage()">Adauga limba</button>
					<button class="btn btn-outline-secondary btn-sm" type="button" @click="setDefaultLanguage()" :disabled="defaultLanguage === activeLanguage">Seteaza implicita</button>
					<button class="btn btn-outline-danger btn-sm" type="button" @click="removeLanguage()" :disabled="languages.length <= 1">Sterge limba</button>
				</div>
				<a class="btn btn-outline-light btn-sm" href="logout.php">Delogare</a>
			</div>
		</div>
	</nav>

	<div class="admin-toolbar sticky-top py-3">
		<div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-3">
			<div class="d-flex align-items-center gap-2">
				<span class="badge bg-warning text-dark" x-text="status"></span>
				<span class="text-muted small">Limba activa: <strong x-text="languageLabel(activeLanguage)"></strong></span>
			</div>
			<div class="d-flex flex-wrap gap-2">
				<button class="btn btn-outline-light" type="button" @click="reset()">Reset la datele salvate</button>
				<button class="btn btn-warning text-dark" type="button" :disabled="saving" @click="save()">
					<span x-show="!saving">Salveaza modificarile</span>
					<span x-show="saving">Se salveaza...</span>
				</button>
			</div>
		</div>
	</div>

	<div class="container my-5" @input="markDirty()">
		<?php if ($showPasswordWarning): ?>
			<div class="alert alert-warning" role="alert">
				Parola implicita este activa. Editeaza <code>config/commands-config.php</code> pentru a seta o parola puternica.
			</div>
		<?php endif; ?>

		<div class="row g-4">
			<div class="col-12">
				<div class="card card-dark p-4">
					<h2 class="h5 mb-3">Informatii generale</h2>
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label">Titlu site</label>
							<input class="form-control" type="text" x-model="payload.meta.siteTitle">
						</div>
						<div class="col-md-4">
							<label class="form-label">Tagline</label>
							<input class="form-control" type="text" x-model="payload.meta.siteTagline">
						</div>
						<div class="col-md-4">
							<label class="form-label">Discord URL</label>
							<input class="form-control" type="url" x-model="payload.meta.discordUrl">
						</div>
					</div>
				</div>
			</div>

			<div class="col-12">
				<div class="card card-dark p-4">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h5 mb-0">Sectiunea hero</h2>
						<button class="btn btn-sm btn-outline-secondary" @click="toggleLogo()">Logo vizibil: <strong x-text="payload.hero.logo.visible ? 'da' : 'nu'"></strong></button>
					</div>
					<div class="row g-3">
						<div class="col-lg-4">
							<label class="form-label">Titlu</label>
							<input class="form-control" type="text" x-model="payload.hero.title">
						</div>
						<div class="col-lg-8">
							<label class="form-label">Descriere</label>
							<textarea class="form-control" rows="3" x-model="payload.hero.description"></textarea>
						</div>
						<div class="col-md-6">
							<label class="form-label">Buton principal - text</label>
							<input class="form-control" type="text" x-model="payload.hero.primaryCta.label">
						</div>
						<div class="col-md-6">
							<label class="form-label">Buton principal - link</label>
							<input class="form-control" type="text" x-model="payload.hero.primaryCta.href">
						</div>
						<div class="col-md-6">
							<label class="form-label">Buton secundar - text</label>
							<input class="form-control" type="text" x-model="payload.hero.secondaryCta.label">
						</div>
						<div class="col-md-6">
							<label class="form-label">Buton secundar - link</label>
							<input class="form-control" type="text" x-model="payload.hero.secondaryCta.href">
						</div>
						<div class="col-12" x-show="payload.hero.logo.visible">
							<label class="form-label">Logo URL</label>
							<input class="form-control" type="text" x-model="payload.hero.logo.url">
						</div>
					</div>
				</div>
			</div>

			<div class="col-12">
				<div class="card card-dark p-4">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h5 mb-0">Ghid rapid</h2>
						<button class="btn btn-sm btn-outline-light" @click="addGuideStep()">Adauga pas</button>
					</div>
					<div class="row g-3 mb-3">
						<div class="col-lg-4">
							<label class="form-label">Titlu ghid</label>
							<input class="form-control" type="text" x-model="payload.guide.title" @input="markDirty()">
						</div>
						<div class="col-lg-8">
							<label class="form-label">Descriere ghid</label>
							<textarea class="form-control" rows="2" x-model="payload.guide.description" @input="markDirty()"></textarea>
						</div>
					</div>
					<div class="row g-4" x-show="payload.guide.steps.length" x-cloak>
						<template x-for="(step, index) in payload.guide.steps" :key="index">
							<div class="col-md-6">
								<div class="border border-secondary border-opacity-25 rounded-3 p-3 h-100">
									<div class="d-flex justify-content-between align-items-start mb-2">
										<h3 class="h6">Pas <span x-text="index + 1"></span></h3>
										<button class="btn btn-sm btn-outline-danger" @click="removeGuideStep(index)">Sterge</button>
									</div>
									<label class="form-label">Titlu</label>
									<input class="form-control mb-2" type="text" x-model="step.title">
									<label class="form-label">Rezumat</label>
									<textarea class="form-control mb-2" rows="2" x-model="step.summary"></textarea>
									<label class="form-label">Comenzi</label>
									<input class="form-control mb-2" type="text" placeholder="Separat prin virgule" x-model="step._commands">
									<small class="text-muted">Ex: /claim, /trust, /untrust</small>
								</div>
							</div>
						</template>
					</div>
					<p class="text-muted" x-show="!payload.guide.steps.length">Niciun pas definit.</p>
				</div>
			</div>

			<div class="col-12">
				<div class="card card-dark p-4">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h5 mb-0" x-text="payload.catalog.title || 'Categorii comenzi'"></h2>
						<button class="btn btn-sm btn-outline-light" type="button" @click="addCategory()">Adauga categorie</button>
					</div>
					<div class="row g-3 mb-4">
						<div class="col-lg-4">
							<label class="form-label">Titlu sectiune</label>
							<input class="form-control" type="text" x-model="payload.catalog.title" @input="markDirty()">
						</div>
						<div class="col-lg-4">
							<label class="form-label">Descriere sectiune</label>
							<textarea class="form-control" rows="2" x-model="payload.catalog.subtitle" @input="markDirty()"></textarea>
						</div>
						<div class="col-lg-2">
							<label class="form-label">Label buton</label>
							<input class="form-control" type="text" x-model="payload.catalog.ctaLabel" @input="markDirty()">
						</div>
						<div class="col-lg-2">
							<label class="form-label">Link buton</label>
							<input class="form-control" type="url" x-model="payload.catalog.ctaHref" @input="markDirty()">
						</div>
					</div>
					<div class="accordion" id="categoryAccordion">
						<template x-for="(category, catIndex) in payload.catalog.categories" :key="'cat-' + catIndex">
							<div class="accordion-item border-secondary border-opacity-25 text-light">
								<h2 class="accordion-header" :id="'cat-' + catIndex">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" :data-bs-target="'#collapse-' + catIndex">
										<span class="me-3">Categorie <span x-text="catIndex + 1"></span>:</span>
										<strong x-text="payload.catalog.categories[catIndex].title || 'Fara titlu'"></strong>
									</button>
								</h2>
								<div :id="'collapse-' + catIndex" class="accordion-collapse collapse" :data-bs-parent="'#categoryAccordion'">
									<div class="accordion-body">
										<div class="row g-3 mb-3">
											<div class="col-md-4">
												<label class="form-label">Titlu</label>
												<input class="form-control" type="text" x-model="payload.catalog.categories[catIndex].title" @input="markDirty()">
											</div>
											<div class="col-md-8">
												<label class="form-label">Rezumat</label>
												<textarea class="form-control" rows="2" x-model="payload.catalog.categories[catIndex].summary" @input="markDirty()"></textarea>
											</div>
										</div>
										<div class="d-flex justify-content-between align-items-center mb-2">
											<h3 class="h6 mb-0">Comenzi</h3>
											<button class="btn btn-sm btn-outline-secondary" type="button" @click="addCommand(catIndex)">Comanda noua</button>
										</div>
										<div class="list-group">
											<template x-for="(command, cmdIndex) in payload.catalog.categories[catIndex].commands" :key="'cmd-' + catIndex + '-' + cmdIndex">
												<div class="list-group-item text-light border-secondary border-opacity-25 mb-2 rounded-3">
													<div class="row g-2 align-items-center">
														<div class="col-md-3">
															<input class="form-control" type="text" placeholder="Label" x-model="payload.catalog.categories[catIndex].commands[cmdIndex].label" @input="markDirty()">
														</div>
														<div class="col-md-3">
															<input class="form-control" type="text" placeholder="Sintaxa" x-model="payload.catalog.categories[catIndex].commands[cmdIndex].usage" @input="markDirty()">
														</div>
														<div class="col-md-5">
															<input class="form-control" type="text" placeholder="Descriere" x-model="payload.catalog.categories[catIndex].commands[cmdIndex].description" @input="markDirty()">
														</div>
														<div class="col-md-1 d-grid">
															<button class="btn btn-outline-danger" type="button" @click="removeCommand(catIndex, cmdIndex)"><i class="bi bi-x"></i></button>
														</div>
													</div>
												</div>
											</template>
										</div>
										<div class="text-end mt-3">
											<button class="btn btn-outline-danger btn-sm" type="button" @click="removeCategory(catIndex)">Sterge categoria</button>
										</div>
									</div>
								</div>
							</div>
						</template>
					</div>
					<p class="text-muted" x-show="!payload.catalog.categories.length">Nicio categorie definita.</p>
				</div>
			</div>

			<div class="col-lg-6">
				<div class="card card-dark p-4">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h5 mb-0" x-text="payload.tips.title || 'Sfaturi rapide'"></h2>
						<button class="btn btn-sm btn-outline-light" @click="addTip()">Adauga tip</button>
					</div>
					<div class="mb-3">
						<label class="form-label">Titlu sectiune</label>
						<input class="form-control" type="text" x-model="payload.tips.title" @input="markDirty()">
					</div>
					<div class="vstack gap-2">
						<template x-for="(tip, index) in payload.tips.items" :key="index">
							<div class="input-group">
								<input class="form-control" type="text" x-model="payload.tips.items[index]">
								<button class="btn btn-outline-danger" @click="removeTip(index)"><i class="bi bi-x"></i></button>
							</div>
						</template>
					</div>
				</div>
			</div>

			<div class="col-lg-6">
				<div class="card card-dark p-4">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h2 class="h5 mb-0" x-text="payload.faq.title || 'Intrebari frecvente'"></h2>
						<button class="btn btn-sm btn-outline-light" @click="addFaq()">Adauga intrebare</button>
					</div>
					<div class="mb-3">
						<label class="form-label">Titlu sectiune</label>
						<input class="form-control" type="text" x-model="payload.faq.title" @input="markDirty()">
					</div>
					<div class="vstack gap-3">
						<template x-for="(item, index) in payload.faq.items" :key="index">
							<div class="border border-secondary border-opacity-25 rounded-3 p-3">
								<label class="form-label">Intrebare</label>
								<input class="form-control mb-2" type="text" x-model="item.question">
								<label class="form-label">Raspuns</label>
								<textarea class="form-control mb-2" rows="2" x-model="item.answer"></textarea>
								<button class="btn btn-outline-danger btn-sm" @click="removeFaq(index)">Sterge</button>
							</div>
						</template>
					</div>
				</div>
			</div>

			<div class="col-12">
				<div class="card card-dark p-4">
					<h2 class="h5 mb-3">Footer</h2>
					<div class="row g-3 mb-3">
						<div class="col-lg-6">
							<label class="form-label">Text principal</label>
							<input class="form-control" type="text" x-model="payload.footer.text">
						</div>
					</div>
					<div class="d-flex justify-content-between align-items-center mb-3">
						<h3 class="h6 mb-0">Linkuri</h3>
						<button class="btn btn-sm btn-outline-secondary" @click="addFooterLink()">Link nou</button>
					</div>
					<div class="vstack gap-3">
						<template x-for="(link, index) in payload.footer.links" :key="index">
							<div class="row g-2 align-items-center">
								<div class="col-md-4">
									<input class="form-control" type="text" placeholder="Eticheta" x-model="link.label">
								</div>
								<div class="col-md-5">
									<input class="form-control" type="url" placeholder="URL" x-model="link.href">
								</div>
								<div class="col-md-2">
									<input class="form-control" type="text" placeholder="target" x-model="link.target">
								</div>
								<div class="col-md-1 d-grid">
									<button class="btn btn-outline-danger" @click="removeFooterLink(index)"><i class="bi bi-x"></i></button>
								</div>
							</div>
						</template>
					</div>
				</div>
			</div>
		</div>

	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

	<template x-if="toast.visible">
		<div class="position-fixed bottom-0 end-0 p-4" style="z-index: 1080;">
			<div class="toast show bg-success text-white">
				<div class="toast-body" x-text="toast.message"></div>
			</div>
		</div>
	</template>

	<script>
		function commandEditor() {
			return {
				fullPayload: <?php echo $initialPayload; ?>,
				initialState: <?php echo $initialPayload; ?>,
				languageLabels: <?php echo $languageLabelsJson; ?>,
				translations: {},
				languages: [],
				defaultLanguage: 'ro',
				activeLanguage: 'ro',
				payload: {},
				status: 'Ne-salvat',
				saving: false,
				toast: {
					visible: false,
					message: ''
				},
				init() {
					this.bootstrap();
				},
				bootstrap() {
					const source = JSON.parse(JSON.stringify(this.fullPayload));
					const candidateLanguages = Array.isArray(source.languages) ? source.languages : [];
					const translationsMap = typeof source.translations === 'object' && source.translations !== null ? source.translations : {};
					this.languages = Array.from(new Set(candidateLanguages.map(code => this.normaliseCode(code)).filter(Boolean)));
					if (!this.languages.length) {
						this.languages = Object.keys(translationsMap).map(code => this.normaliseCode(code)).filter(Boolean);
					}
					if (!this.languages.length) {
						this.languages = ['ro'];
					}
					this.translations = {};
					this.languages.forEach(code => {
						const translation = translationsMap[code] && typeof translationsMap[code] === 'object' ? translationsMap[code] : {};
						this.translations[code] = JSON.parse(JSON.stringify(translation));
					});
					this.languages.forEach(code => {
						if (!this.translations[code]) {
							this.translations[code] = {};
						}
					});
					const defaultCode = this.normaliseCode(source.defaultLanguage ?? '');
					this.defaultLanguage = defaultCode && this.languages.includes(defaultCode) ? defaultCode : this.languages[0];
					this.activeLanguage = this.defaultLanguage;
					this.payload = this.translations[this.activeLanguage];
					this.ensureStructures();
					this.prepareGuideTokens();
					this.prepareCatalogStructures();
				},
				normaliseCode(code) {
					const value = String(code ?? '').trim().toLowerCase();
					return /^[a-z0-9_-]{2,10}$/.test(value) ? value : '';
				},
				onLanguageChange(event) {
					this.setLanguage(event.target.value);
				},
				setLanguage(code) {
					const normalised = this.normaliseCode(code);
					if (!normalised || !this.translations[normalised]) {
						return;
					}
					this.activeLanguage = normalised;
					this.payload = this.translations[this.activeLanguage];
					this.ensureStructures();
					this.prepareGuideTokens();
					this.prepareCatalogStructures();
				},
				addLanguage() {
					const input = prompt('Cod limba (ex: ro, en)') || '';
					const code = this.normaliseCode(input);
					if (!code) {
						return;
					}
					if (!this.languages.includes(code)) {
						this.languages.push(code);
						this.translations[code] = JSON.parse(JSON.stringify(this.payload));
						if (!this.languageLabels[code]) {
							this.languageLabels[code] = {
								label: code.toUpperCase(),
								countryCode: '',
								flagEmoji: '',
								flagIconClass: ''
							};
						}
					}
					this.setLanguage(code);
					this.markDirty();
				},
				setDefaultLanguage() {
					this.defaultLanguage = this.activeLanguage;
					this.markDirty();
				},
				removeLanguage() {
					if (this.languages.length <= 1) {
						return;
					}
					const target = this.activeLanguage;
					const label = this.languageLabel(target);
					if (!confirm(`Stergi limba "${label}"? Contintul specific acesteia va fi pierdut dupa salvare.`)) {
						return;
					}
					this.languages = this.languages.filter(code => code !== target);
					delete this.translations[target];
					if (!this.languages.length) {
						this.languages = ['ro'];
						if (!this.translations['ro']) {
							this.translations['ro'] = {};
						}
					}
					if (!this.languages.includes(this.defaultLanguage)) {
						this.defaultLanguage = this.languages[0];
					}
					this.activeLanguage = this.languages.includes(target) ? target : this.defaultLanguage;
					if (!this.languages.includes(this.activeLanguage)) {
						this.activeLanguage = this.languages[0];
					}
					if (!this.translations[this.activeLanguage]) {
						this.translations[this.activeLanguage] = {};
					}
					this.payload = this.translations[this.activeLanguage];
					this.ensureStructures();
					this.prepareGuideTokens();
					this.prepareCatalogStructures();
					this.markDirty();
				},
				languageLabel(code) {
					const meta = this.languageMetadata(code);
					const languageCode = (code || '').toUpperCase();
					const base = meta.label !== '' ? meta.label : languageCode;
					const prefixParts = [];
					if (meta.flagEmoji) {
						prefixParts.push(meta.flagEmoji);
					}
					if (languageCode) {
						prefixParts.push(languageCode);
					}
					const prefix = prefixParts.join(' ');
					const hasDistinctLabel = base !== '' && base !== languageCode;
					const decorated = prefix ? `${prefix}${hasDistinctLabel ? ` · ${base}` : ''}` : base;
					return code === this.defaultLanguage ? `${decorated} (implicit)` : decorated;
				},
				languageMetadata(code) {
					const candidate = this.languageLabels[code];
					if (candidate && typeof candidate === 'object') {
						const label = typeof candidate.label === 'string' ? candidate.label.trim() : '';
						const country = typeof candidate.countryCode === 'string' ? candidate.countryCode.trim().toUpperCase() : '';
						let flagEmoji = typeof candidate.flagEmoji === 'string' ? candidate.flagEmoji.trim() : '';
						const flagIconClass = typeof candidate.flagIconClass === 'string' ? candidate.flagIconClass.trim() : (country ? `fi fi-${country.toLowerCase()}` : '');
						if (!flagEmoji && country.length === 2) {
							flagEmoji = country
								.split('')
								.map(char => String.fromCodePoint(char.toUpperCase().charCodeAt(0) + 127397))
								.join('');
						}
						return {
							label,
							countryCode: country,
							flagEmoji,
							flagIconClass,
							languageCode: (code || '').toUpperCase()
						};
					}
					if (typeof candidate === 'string') {
						const label = candidate.trim();
						return {
							label,
							countryCode: '',
							flagEmoji: '',
							flagIconClass: ''
						};
					}
					return {
						label: '',
						countryCode: '',
						flagEmoji: '',
						flagIconClass: '',
						languageCode: (code || '').toUpperCase()
					};
				},
				ensureStructures() {
					const target = this.payload;
					target.meta = target.meta || {};
					target.hero = target.hero || {
						primaryCta: {},
						secondaryCta: {},
						logo: {}
					};
					target.hero.primaryCta = target.hero.primaryCta || {};
					target.hero.secondaryCta = target.hero.secondaryCta || {};
					target.hero.logo = target.hero.logo || {};
					target.infoStrip = target.infoStrip || {
						cards: []
					};
					target.guide = target.guide || {
						steps: []
					};
					target.catalog = target.catalog || {
						categories: []
					};
					target.catalog.title = typeof target.catalog.title === 'string' ? target.catalog.title : '';
					target.catalog.subtitle = typeof target.catalog.subtitle === 'string' ?
						target.catalog.subtitle :
						(typeof target.catalog.description === 'string' ? target.catalog.description : '');
					target.catalog.description = typeof target.catalog.description === 'string' ? target.catalog.description : '';
					target.catalog.ctaLabel = typeof target.catalog.ctaLabel === 'string' ? target.catalog.ctaLabel : '';
					target.catalog.ctaHref = typeof target.catalog.ctaHref === 'string' ? target.catalog.ctaHref : '';
					if (!target.catalog.ctaHref && target.meta.discordUrl) {
						target.catalog.ctaHref = target.meta.discordUrl;
					}
					target.tips = target.tips || {
						items: []
					};
					target.tips.title = typeof target.tips.title === 'string' ? target.tips.title : '';
					target.faq = target.faq || {
						items: []
					};
					target.faq.title = typeof target.faq.title === 'string' ? target.faq.title : '';
					target.footer = target.footer || {
						links: []
					};
					if (!Array.isArray(target.catalog.categories)) {
						target.catalog.categories = [];
					}
					target.guide.title = target.guide.title || '';
					target.guide.description = target.guide.description || '';
					target.catalog.categories = target.catalog.categories.map(originalCategory => {
						const category = originalCategory || {};
						const commands = Array.isArray(category.commands) ? category.commands : [];
						return {
							title: category.title || '',
							summary: category.summary || '',
							commands: commands.map(originalCommand => {
								const command = originalCommand || {};
								return {
									label: command.label || '',
									usage: command.usage || '',
									description: command.description || ''
								};
							})
						};
					});
					if (!Array.isArray(target.tips.items)) {
						target.tips.items = [];
					}
					if (!Array.isArray(target.faq.items)) {
						target.faq.items = [];
					}
					if (!Array.isArray(target.footer.links)) {
						target.footer.links = [];
					}
				},
				prepareGuideTokens() {
					const steps = Array.isArray(this.payload.guide?.steps) ? this.payload.guide.steps : [];
					this.payload.guide.steps = steps.map(step => ({
						...step,
						_commands: Array.isArray(step.commands) ? step.commands.join(', ') : (typeof step._commands === 'string' ? step._commands : '')
					}));
				},
				prepareCatalogStructures() {
					const categories = Array.isArray(this.payload.catalog?.categories) ? this.payload.catalog.categories : [];
					categories.forEach((category, index) => {
						const safeCategory = category || {};
						if (!Array.isArray(safeCategory.commands)) {
							safeCategory.commands = [];
						}
						safeCategory.commands = safeCategory.commands.map(originalCommand => {
							const command = originalCommand || {};
							return {
								label: command.label || '',
								usage: command.usage || '',
								description: command.description || ''
							};
						});
						categories[index] = safeCategory;
					});
					this.payload.catalog.categories = categories;
				},
				markDirty() {
					if (!this.saving) {
						this.status = 'Ne-salvat';
					}
				},
				toggleLogo() {
					this.payload.hero.logo.visible = !this.payload.hero.logo.visible;
					this.markDirty();
				},
				addGuideStep() {
					this.payload.guide.steps.push({
						title: 'Titlu nou',
						summary: 'Adauga detalii',
						_commands: '/spawn, /home'
					});
					this.markDirty();
				},
				removeGuideStep(index) {
					this.payload.guide.steps.splice(index, 1);
					this.markDirty();
				},
				addCategory() {
					this.payload.catalog.categories.push({
						title: 'Categorie noua',
						summary: 'Descriere',
						commands: []
					});
					this.markDirty();
				},
				removeCategory(index) {
					this.payload.catalog.categories.splice(index, 1);
					this.markDirty();
				},
				addCommand(catIndex) {
					const category = this.payload.catalog.categories[catIndex];
					if (!category.commands) category.commands = [];
					category.commands.push({
						label: '/cmd',
						usage: '/cmd',
						description: 'Descriere'
					});
					this.markDirty();
				},
				removeCommand(catIndex, cmdIndex) {
					this.payload.catalog.categories[catIndex].commands.splice(cmdIndex, 1);
					this.markDirty();
				},
				addTip() {
					if (!Array.isArray(this.payload.tips.items)) {
						this.payload.tips.items = [];
					}
					this.payload.tips.items.push('Tip nou');
					this.markDirty();
				},
				removeTip(index) {
					this.payload.tips.items.splice(index, 1);
					this.markDirty();
				},
				addFaq() {
					if (!Array.isArray(this.payload.faq.items)) {
						this.payload.faq.items = [];
					}
					this.payload.faq.items.push({
						question: 'Intrebare',
						answer: 'Raspuns'
					});
					this.markDirty();
				},
				removeFaq(index) {
					this.payload.faq.items.splice(index, 1);
					this.markDirty();
				},
				addFooterLink() {
					if (!Array.isArray(this.payload.footer.links)) {
						this.payload.footer.links = [];
					}
					this.payload.footer.links.push({
						label: 'Link nou',
						href: 'https://',
						target: '_blank',
						rel: ''
					});
					this.markDirty();
				},
				removeFooterLink(index) {
					this.payload.footer.links.splice(index, 1);
					this.markDirty();
				},
				reset() {
					this.fullPayload = JSON.parse(JSON.stringify(this.initialState));
					this.bootstrap();
					this.status = 'Resetat';
				},
				serialiseTranslation(translation) {
					const cloned = JSON.parse(JSON.stringify(translation || {}));
					cloned.meta = cloned.meta || {};
					cloned.hero = cloned.hero || {};
					cloned.hero.primaryCta = cloned.hero.primaryCta || {};
					cloned.hero.secondaryCta = cloned.hero.secondaryCta || {};
					cloned.hero.logo = cloned.hero.logo || {};
					cloned.infoStrip = cloned.infoStrip || {};
					cloned.infoStrip.cards = Array.isArray(cloned.infoStrip.cards) ? cloned.infoStrip.cards.filter(card => card && typeof card === 'object') : [];
					cloned.guide = cloned.guide || {};
					cloned.guide.title = String(cloned.guide.title || '').trim();
					cloned.guide.description = String(cloned.guide.description || '').trim();
					cloned.guide.steps = Array.isArray(cloned.guide.steps) ? cloned.guide.steps.map(step => {
						const commandsSource = typeof step._commands === 'string' ?
							step._commands :
							Array.isArray(step.commands) ? step.commands.join(',') : '';
						const commands = commandsSource.split(',').map(cmd => cmd.trim()).filter(Boolean);
						return {
							title: step.title || '',
							summary: step.summary || '',
							commands
						};
					}) : [];
					cloned.catalog = cloned.catalog || {};
					cloned.catalog.title = String(cloned.catalog.title || '').trim();
					const catalogSubtitle = typeof cloned.catalog.subtitle === 'string' ? cloned.catalog.subtitle : (typeof cloned.catalog.description === 'string' ? cloned.catalog.description : '');
					cloned.catalog.subtitle = String(catalogSubtitle || '').trim();
					cloned.catalog.description = String(cloned.catalog.description || '').trim();
					if (cloned.catalog.subtitle !== '' && cloned.catalog.description === '') {
						cloned.catalog.description = cloned.catalog.subtitle;
					}
					cloned.catalog.ctaLabel = String(cloned.catalog.ctaLabel || '').trim();
					cloned.catalog.ctaHref = String(cloned.catalog.ctaHref || '').trim();
					cloned.catalog.categories = Array.isArray(cloned.catalog.categories) ? cloned.catalog.categories.map(category => {
						const commands = Array.isArray(category.commands) ? category.commands : [];
						return {
							title: category.title || '',
							summary: category.summary || '',
							commands: commands.map(command => ({
								label: command.label || '',
								usage: command.usage || '',
								description: command.description || ''
							}))
						};
					}) : [];
					cloned.tips = cloned.tips || {};
					cloned.tips.title = String(cloned.tips.title || '').trim();
					cloned.tips.items = Array.isArray(cloned.tips.items) ? cloned.tips.items.map(item => String(item || '').trim()).filter(Boolean) : [];
					cloned.faq = cloned.faq || {};
					cloned.faq.title = String(cloned.faq.title || '').trim();
					cloned.faq.items = Array.isArray(cloned.faq.items) ? cloned.faq.items.map(item => ({
						question: item.question || '',
						answer: item.answer || ''
					})) : [];
					cloned.footer = cloned.footer || {};
					cloned.footer.text = cloned.footer.text || '';
					cloned.footer.links = Array.isArray(cloned.footer.links) ? cloned.footer.links.map(link => ({
						label: link.label || '',
						href: link.href || '',
						target: link.target || '',
						rel: link.rel || ''
					})) : [];
					return cloned;
				},
				cleanPayloadForSave() {
					this.languages = Array.from(new Set(this.languages.map(code => this.normaliseCode(code)).filter(Boolean)));
					if (!this.languages.length) {
						this.languages = ['ro'];
					}
					const cleanedTranslations = {};
					this.languages.forEach(code => {
						cleanedTranslations[code] = this.serialiseTranslation(this.translations[code] || {});
					});
					const fallbackDefault = this.languages.includes(this.defaultLanguage) ? this.defaultLanguage : this.languages[0];
					return {
						languages: [...this.languages],
						defaultLanguage: fallbackDefault,
						translations: cleanedTranslations
					};
				},
				async save() {
					this.saving = true;
					const cleaned = this.cleanPayloadForSave();
					try {
						const response = await fetch('save.php', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-Token': '<?php echo $csrfToken; ?>'
							},
							body: JSON.stringify(cleaned)
						});
						if (!response.ok) {
							throw new Error('Eroare la salvare');
						}
						const payload = await response.json();
						if (!payload.success) {
							throw new Error(payload.message || 'Nu s-a putut salva.');
						}
						this.fullPayload = JSON.parse(JSON.stringify(cleaned));
						this.initialState = JSON.parse(JSON.stringify(cleaned));
						this.bootstrap();
						this.status = 'Salvat';
						this.toast.message = 'Modificarile au fost salvate';
						this.toast.visible = true;
						setTimeout(() => (this.toast.visible = false), 2500);
					} catch (error) {
						alert(error.message);
						this.status = 'Eroare';
					} finally {
						this.saving = false;
					}
				}
			};
		}
	</script>
</body>

</html>