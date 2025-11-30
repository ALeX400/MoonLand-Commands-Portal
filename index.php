<?php

declare(strict_types=1);

require __DIR__ . '/lib/DataStore.php';

$config = include __DIR__ . '/config/commands-config.php';
$dataPath = $config['paths']['data'] ?? (__DIR__ . '/config/commands-data.json');
$dataStore = new DataStore($dataPath);
$data = $dataStore->read();

function e(?string $value): string
{
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDisplayDate(string $value, string $format): string
{
	$timestamp = strtotime($value);
	if ($timestamp === false) {
		return date($format);
	}

	return date($format, $timestamp);
}

function replaceTokens(string $text, array $replacements): string
{
	return strtr($text, $replacements);
}

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
			$translations[$normalised] = extractLegacyTranslation(is_array($translation) ? $translation : []);
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

function languageMetadata(string $code): array
{
	global $languageLabels;
	$normalised = normaliseLanguageCode($code);
	$key = $normalised !== '' ? $normalised : strtolower(trim($code));
	$languageCode = strtoupper($normalised !== '' ? $normalised : (trim((string) $code) !== '' ? trim((string) $code) : ''));
	$candidate = $key !== '' ? ($languageLabels[$key] ?? null) : null;
	$label = '';
	$countryCode = '';
	$flagEmoji = '';
	$flagIconClass = '';
	if (is_array($candidate)) {
		$label = trim((string) ($candidate['label'] ?? ''));
		$countryCode = strtoupper(trim((string) ($candidate['countryCode'] ?? '')));
		$flagEmoji = trim((string) ($candidate['flagEmoji'] ?? ''));
		$flagIconClass = trim((string) ($candidate['flagIconClass'] ?? ''));
	} elseif (is_string($candidate)) {
		$label = trim($candidate);
	}
	if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode)) {
		$countryCode = '';
	}
	if ($flagEmoji === '' && $countryCode !== '') {
		$flagEmoji = countryCodeToFlagEmoji($countryCode);
	}
	if ($flagIconClass === '' && $countryCode !== '') {
		$flagIconClass = 'fi fi-' . strtolower($countryCode);
	}
	return [
		'label' => $label,
		'countryCode' => $countryCode,
		'flagEmoji' => $flagEmoji,
		'flagIconClass' => $flagIconClass,
		'languageCode' => $languageCode,
	];
}

function languageLabel(string $code): string
{
	$meta = languageMetadata($code);
	$normalised = normaliseLanguageCode($code);
	$fallback = strtoupper($normalised !== '' ? $normalised : (trim((string) $code) !== '' ? trim((string) $code) : '??'));
	$languageCode = $meta['languageCode'] !== '' ? $meta['languageCode'] : $fallback;
	$label = $meta['label'] !== '' ? $meta['label'] : $languageCode;
	return $languageCode !== '' ? sprintf('%s (%s)', $label, $languageCode) : $label;
}

function buildLanguageUrl(string $code): string
{
	$params = $_GET;
	$params['lang'] = $code;
	$path = $_SERVER['SCRIPT_NAME'] ?? '';
	if ($path === '') {
		$path = '/';
	}
	$query = http_build_query($params);
	return $query === '' ? $path : $path . '?' . $query;
}

$languageLabelsPath = $config['paths']['languageLabels'] ?? (__DIR__ . '/config/languages.json');
$languageLabels = loadLanguageLabels($languageLabelsPath);

$payload = prepareMultilingualPayload($data);
$languages = $payload['languages'];
$defaultLanguage = $payload['defaultLanguage'];
$translations = $payload['translations'];

$requestedLanguage = normaliseLanguageCode((string) ($_GET['lang'] ?? ''));
if ($requestedLanguage === '' || !isset($translations[$requestedLanguage])) {
	$requestedLanguage = $defaultLanguage;
}

$activeLanguage = $requestedLanguage;
$activeTranslation = $translations[$activeLanguage] ?? reset($translations);

$meta = is_array($activeTranslation['meta'] ?? null) ? $activeTranslation['meta'] : [];
$hero = is_array($activeTranslation['hero'] ?? null) ? $activeTranslation['hero'] : [];
$infoStrip = is_array($activeTranslation['infoStrip'] ?? null) ? $activeTranslation['infoStrip'] : [];
$guide = is_array($activeTranslation['guide'] ?? null) ? $activeTranslation['guide'] : [];
$catalog = is_array($activeTranslation['catalog'] ?? null) ? $activeTranslation['catalog'] : [];
$tips = is_array($activeTranslation['tips'] ?? null) ? $activeTranslation['tips'] : [];
$faq = is_array($activeTranslation['faq'] ?? null) ? $activeTranslation['faq'] : [];
$footer = is_array($activeTranslation['footer'] ?? null) ? $activeTranslation['footer'] : [];

$siteTitle = trim((string) ($meta['siteTitle'] ?? 'MoonLand Commands'));
$siteTagline = trim((string) ($meta['siteTagline'] ?? 'Documentatie rapida pentru jucatori MoonLand'));
$discordUrl = trim((string) ($meta['discordUrl'] ?? 'https://discord.moonland.ro/'));

$rawLastUpdate = trim((string) ($meta['lastUpdated'] ?? ''));
if ($rawLastUpdate === '') {
	$rawLastUpdate = $dataStore->lastModified('Y-m-d');
}

$lastUpdateDisplay = formatDisplayDate($rawLastUpdate, 'd M Y');

$infoStripEnabled = !empty($infoStrip['enabled']) && !empty($infoStrip['cards']) && is_array($infoStrip['cards']);
$guideSteps = is_array($guide['steps'] ?? null) ? $guide['steps'] : [];
$catalogCategories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
$tipsItems = is_array($tips['items'] ?? null) ? $tips['items'] : [];
$faqItems = is_array($faq['items'] ?? null) ? $faq['items'] : [];
$catalogSubtitle = trim((string) ($catalog['subtitle'] ?? $catalog['description'] ?? ''));
$catalogCtaLabel = trim((string) ($catalog['ctaLabel'] ?? ''));
$catalogCtaHref = trim((string) ($catalog['ctaHref'] ?? $catalog['ctaUrl'] ?? ''));
if ($catalogCtaHref === '') {
	$catalogCtaHref = $discordUrl;
}
$tipsIntro = trim((string) ($tips['description'] ?? ''));
?>
<!DOCTYPE html>
<html lang="<?php echo e($activeLanguage); ?>">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo e($siteTitle); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.3.2/css/flag-icons.min.css">
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
				radial-gradient(circle at 20% 5%, rgba(223, 156, 112, 0.18), transparent 55%),
				rgba(17, 6, 28, 1);
			color: #f8f8ff;
			min-height: 100vh;
		}

		::selection {
			background: rgba(223, 156, 112, 0.65);
			color: #130821;
		}

		main {
			position: relative;
			z-index: 1;
		}

		.hero-card {
			background: linear-gradient(145deg, rgba(45, 15, 73, 0.95), rgba(17, 6, 28, 0.9));
			border-radius: 28px;
			border: 1px solid rgba(223, 156, 112, 0.25);
			box-shadow: 0 24px 70px rgba(11, 3, 22, 0.55);
		}

		.language-toggle .btn {
			min-width: 90px;
		}

		.language-toggle .fi {
			width: 1.4rem;
			height: 1rem;
			border-radius: 2px;
		}

		.language-toggle .btn.btn-warning {
			font-weight: 600;
			box-shadow: inset 0 0 0 1px rgba(17, 6, 28, 0.25);
		}

		.hero-card .badge {
			background: rgba(223, 156, 112, 0.18);
			color: #fdf2e9;
			border-radius: 999px;
		}

		.card-ml {
			background: var(--ml-surface);
			border: 1px solid rgba(223, 156, 112, 0.18);
			border-radius: 20px;
			box-shadow: 0 16px 45px rgba(11, 3, 22, 0.45);
			transition: transform 0.2s ease, border-color 0.2s ease;
		}

		.card-ml:hover {
			border-color: rgba(223, 156, 112, 0.32);
			transform: translateY(-4px);
		}

		.text-light-emphasis,
		.text-secondary {
			color: rgba(248, 248, 255, 0.82) !important;
		}

		.badge,
		.text-muted {
			color: rgba(248, 248, 255, 0.72) !important;
		}

		.section-title {
			font-weight: 700;
			letter-spacing: 0.03em;
			color: var(--ml-accent);
		}

		.command-chip {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			border-radius: 999px;
			border: 1px solid rgba(223, 156, 112, 0.35);
			background: rgba(223, 156, 112, 0.12);
			padding: 6px 14px;
			font-family: "Fira Code", "Consolas", monospace;
			font-size: 0.9rem;
		}

		.footer-links a {
			color: rgba(255, 255, 255, 0.86);
			text-decoration: none;
			border-bottom: 1px solid transparent;
			padding-bottom: 2px;
		}

		.footer-links a:hover {
			border-color: rgba(223, 156, 112, 0.6);
			color: var(--ml-accent);
		}

		.glass-band {
			background: rgba(255, 255, 255, 0.05);
			border: 1px solid rgba(223, 156, 112, 0.18);
			border-radius: 18px;
			backdrop-filter: blur(10px);
		}

		.info-card strong {
			display: block;
			color: var(--ml-accent);
			font-size: 0.95rem;
		}

		.info-card span {
			color: rgba(255, 255, 255, 0.86);
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

		.list-group-item {
			border: none;
		}

		.list-group-item+.list-group-item {
			border-top: 1px solid rgba(255, 255, 255, 0.08);
		}
	</style>
</head>

<body>
	<main>
		<section class="py-5">
			<div class="container py-lg-5">
				<div class="hero-card p-4 p-lg-5">
					<?php if (count($languages) > 1): ?>
						<nav class="language-toggle d-flex justify-content-end flex-wrap gap-2 mb-3" aria-label="Selecteaza limba">
							<?php foreach ($languages as $code): ?>
								<?php $isActive = $code === $activeLanguage; ?>
								<?php $meta = languageMetadata($code); ?>
								<a class="btn btn-sm <?php echo $isActive ? 'btn-warning text-dark' : 'btn-outline-light'; ?> d-inline-flex align-items-center gap-2" href="<?php echo e(buildLanguageUrl($code)); ?>">
									<?php if ($meta['flagIconClass'] !== ''): ?>
										<span class="<?php echo e($meta['flagIconClass']); ?>" aria-hidden="true"></span>
									<?php elseif ($meta['flagEmoji'] !== ''): ?>
										<span aria-hidden="true"><?php echo e($meta['flagEmoji']); ?></span>
									<?php endif; ?>
									<span><?php echo e(languageLabel($code)); ?></span>
								</a>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>
					<div class="row g-4 align-items-center">
						<div class="col-lg-7">
							<span class="badge text-uppercase mb-3"><?php echo e($siteTagline); ?></span>
							<h1 class="display-4 fw-bold mb-3"><?php echo e($hero['title'] ?? 'Descopera comenzile MoonLand'); ?></h1>
							<p class="text-secondary fs-5 mb-4"><?php echo e($hero['description'] ?? 'Ghid complet pentru comenzile serverului MoonLand.'); ?></p>
							<div class="d-flex flex-wrap gap-3">
								<a class="btn btn-lg btn-warning text-dark px-4" href="<?php echo e($hero['primaryCta']['href'] ?? '#catalog'); ?>">
									<?php echo e($hero['primaryCta']['label'] ?? 'Catalog comenzi'); ?>
								</a>
								<?php if (!empty($hero['secondaryCta']['href']) && !empty($hero['secondaryCta']['label'])): ?>
									<a class="btn btn-lg btn-outline-light px-4" href="<?php echo e($hero['secondaryCta']['href']); ?>" <?php echo !empty($hero['secondaryCta']['target']) ? ' target="' . e($hero['secondaryCta']['target']) . '"' : ''; ?><?php echo !empty($hero['secondaryCta']['rel']) ? ' rel="' . e($hero['secondaryCta']['rel']) . '"' : ''; ?>>
										<?php echo e($hero['secondaryCta']['label']); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
						<div class="col-lg-5 text-center">
							<?php if (!empty($hero['logo']['visible']) && !empty($hero['logo']['url'])): ?>
								<img src="<?php echo e($hero['logo']['url']); ?>" class="img-fluid" alt="MoonLand logo">
							<?php else: ?>
								<div class="text-uppercase fw-bold">MoonLand Network</div>
							<?php endif; ?>
							<div class="mt-4 text-secondary small">Ultima actualizare: <?php echo e($lastUpdateDisplay); ?></div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<?php if ($infoStripEnabled): ?>
			<section class="pb-4">
				<div class="container">
					<div class="glass-band p-3 p-lg-4">
						<div class="row g-3">
							<?php foreach ($infoStrip['cards'] as $card): ?>
								<?php if (!is_array($card)): continue;
								endif; ?>
								<?php
								$title = e($card['title'] ?? '');
								$text = (string) ($card['description'] ?? $card['text'] ?? '');
								$text = replaceTokens($text, ['{{last_update}}' => $lastUpdateDisplay]);
								?>
								<div class="col-12 col-md-6 col-xl-3">
									<div class="info-card h-100 px-3 py-2">
										<?php if ($title !== ''): ?><strong><?php echo $title; ?></strong><?php endif; ?>
										<?php if ($text !== ''): ?><span><?php echo e($text); ?></span><?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section id="guide" class="py-5">
			<div class="container">
				<div class="row align-items-center g-4">
					<div class="col-lg-4">
						<h2 class="section-title mb-3"><?php echo e($guide['title'] ?? 'Ghid rapid'); ?></h2>
						<p class="text-secondary mb-0"><?php echo e($guide['description'] ?? 'Descopera pasii esentiali pentru a profita la maximum de server.'); ?></p>
					</div>
					<div class="col-lg-8">
						<div class="row g-3">
							<?php foreach ($guideSteps as $step): ?>
								<?php if (!is_array($step)): continue;
								endif; ?>
								<div class="col-md-6">
									<div class="card-ml h-100 p-4">
										<h3 class="h5 fw-semibold mb-3"><?php echo e($step['title'] ?? 'Pas'); ?></h3>
										<p class="text-secondary mb-3"><?php echo e($step['summary'] ?? ($step['description'] ?? 'Descriere indisponibila.')); ?></p>
										<?php if (!empty($step['commands']) && is_array($step['commands'])): ?>
											<div class="d-flex flex-wrap gap-2">
												<?php foreach ($step['commands'] as $token): ?>
													<span class="command-chip"><?php echo e((string) $token); ?></span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section id="catalog" class="py-5">
			<div class="container">
				<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
					<div>
						<h2 class="section-title mb-2"><?php echo e($catalog['title'] ?? 'Catalog complet'); ?></h2>
						<?php if ($catalogSubtitle !== ''): ?>
							<p class="text-secondary mb-0"><?php echo e($catalogSubtitle); ?></p>
						<?php endif; ?>
					</div>
					<?php if ($catalogCtaLabel !== '' && $catalogCtaHref !== ''): ?>
						<a class="btn btn-outline-warning mt-4 mt-lg-0" href="<?php echo e($catalogCtaHref); ?>" target="_blank" rel="noopener"><?php echo e($catalogCtaLabel); ?></a>
					<?php endif; ?>
				</div>
				<div class="row g-4">
					<?php foreach ($catalogCategories as $category): ?>
						<?php if (!is_array($category)): continue;
						endif; ?>
						<div class="col-lg-6">
							<div class="card-ml h-100 p-4">
								<div class="d-flex justify-content-between align-items-start mb-3">
									<h3 class="h5 fw-semibold mb-0"><?php echo e($category['title'] ?? 'Categorie'); ?></h3>
									<span class="badge bg-warning text-dark">Comenzi</span>
								</div>
								<p class="text-secondary mb-4"><?php echo e($category['summary'] ?? 'Descriere indisponibila.'); ?></p>
								<div class="list-group list-group-flush">
									<?php foreach ($category['commands'] ?? [] as $command): ?>
										<?php if (!is_array($command)): continue;
										endif; ?>
										<div class="list-group-item bg-transparent text-white py-3">
											<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
												<span class="fw-semibold"><?php echo e($command['label'] ?? '/cmd'); ?></span>
												<span class="command-chip"><?php echo e($command['usage'] ?? '/cmd'); ?></span>
											</div>
											<p class="text-secondary mb-0 mt-2 small"><?php echo e($command['description'] ?? 'Descriere indisponibila.'); ?></p>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<section id="tips" class="py-5">
			<div class="container">
				<div class="row g-4">
					<div class="col-lg-4">
						<h2 class="section-title mb-3"><?php echo e($tips['title'] ?? 'Sfaturi utile'); ?></h2>
						<?php if ($tipsIntro !== ''): ?>
							<p class="text-secondary mb-0"><?php echo e($tipsIntro); ?></p>
						<?php endif; ?>
					</div>
					<div class="col-lg-8">
						<div class="row g-3">
							<?php foreach ($tipsItems as $tip): ?>
								<div class="col-md-6">
									<div class="card-ml h-100 p-4">
										<p class="mb-0 text-secondary"><?php echo e((string) $tip); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section id="faq" class="py-5">
			<div class="container">
				<h2 class="section-title mb-4"><?php echo e($faq['title'] ?? 'Intrebari frecvente'); ?></h2>
				<div class="accordion" id="faqAccordion">
					<?php foreach ($faqItems as $index => $item): ?>
						<?php if (!is_array($item)): continue;
						endif; ?>
						<div class="accordion-item bg-transparent border-secondary border-opacity-25">
							<h3 class="accordion-header" id="heading-<?php echo (int) $index; ?>">
								<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo (int) $index; ?>" aria-expanded="false" aria-controls="collapse-<?php echo (int) $index; ?>">
									<?php echo e($item['question'] ?? 'Intrebare'); ?>
								</button>
							</h3>
							<div id="collapse-<?php echo (int) $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo (int) $index; ?>" data-bs-parent="#faqAccordion">
								<div class="accordion-body text-secondary">
									<?php echo e($item['answer'] ?? 'Raspuns indisponibil.'); ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	</main>

	<footer class="py-5 mt-5" style="background: rgba(17, 6, 28, 0.85); border-top: 1px solid rgba(223, 156, 112, 0.18);">
		<div class="container d-flex flex-column flex-lg-row justify-content-between gap-3">
			<div>
				<strong><?php echo e($footer['text'] ?? 'MoonLand Network - Comunitate Minecraft'); ?></strong>
				<div class="text-secondary small">Ultima actualizare: <?php echo e($lastUpdateDisplay); ?></div>
			</div>
			<?php if (!empty($footer['links']) && is_array($footer['links'])): ?>
				<div class="footer-links d-flex flex-wrap gap-4">
					<?php foreach ($footer['links'] as $link): ?>
						<?php if (!is_array($link) || empty($link['href']) || empty($link['label'])): continue;
						endif; ?>
						<a href="<?php echo e($link['href']); ?>" target="<?php echo e($link['target'] ?? '_blank'); ?>" rel="<?php echo e($link['rel'] ?? 'noopener'); ?>"><?php echo e($link['label']); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>