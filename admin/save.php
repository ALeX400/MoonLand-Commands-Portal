<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json');

require __DIR__ . '/../lib/DataStore.php';

$config = include __DIR__ . '/../config/commands-config.php';
$dataPath = $config['paths']['data'] ?? (__DIR__ . '/../config/commands-data.json');
$adminConfig = $config['admin'] ?? [];
$sessionKey = $adminConfig['session_key'] ?? 'moonland_commands_admin';
$csrfSessionKey = $sessionKey . '_csrf';

if (empty($_SESSION[$sessionKey])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Neautorizat']);
	exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($csrfHeader === '' || !hash_equals((string) ($_SESSION[$csrfSessionKey] ?? ''), $csrfHeader)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Token CSRF invalid']);
	exit;
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Cerere fara continut']);
	exit;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Structura JSON invalida']);
	exit;
}

$normalised = normalise_payload($data);

$dataStore = new DataStore($dataPath);
if (!$dataStore->write($normalised)) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Nu am putut salva fisierul']);
	exit;
}

echo json_encode(['success' => true]);

function normalise_language_code(string $code): string
{
	$normalised = strtolower(trim($code));
	return preg_match('/^[a-z0-9_-]{2,10}$/', $normalised) ? $normalised : '';
}

function normalise_translation(array $data): array
{
	$data['meta'] = is_array($data['meta'] ?? null) ? $data['meta'] : [];
	$data['hero'] = is_array($data['hero'] ?? null) ? $data['hero'] : [];
	$data['hero']['primaryCta'] = is_array($data['hero']['primaryCta'] ?? null) ? $data['hero']['primaryCta'] : [];
	$data['hero']['secondaryCta'] = is_array($data['hero']['secondaryCta'] ?? null) ? $data['hero']['secondaryCta'] : [];
	$data['hero']['logo'] = is_array($data['hero']['logo'] ?? null) ? $data['hero']['logo'] : [];
	$data['hero']['logo']['visible'] = !empty($data['hero']['logo']['visible']);

	$data['infoStrip'] = is_array($data['infoStrip'] ?? null) ? $data['infoStrip'] : [];
	$infoCards = [];
	foreach (is_array($data['infoStrip']['cards'] ?? null) ? $data['infoStrip']['cards'] : [] as $card) {
		if (!is_array($card)) {
			continue;
		}
		$infoCards[] = [
			'icon' => (string) ($card['icon'] ?? ''),
			'title' => (string) ($card['title'] ?? ''),
			'description' => (string) ($card['description'] ?? ''),
		];
	}
	$data['infoStrip']['cards'] = $infoCards;

	$data['guide'] = is_array($data['guide'] ?? null) ? $data['guide'] : [];
	$steps = [];
	foreach (is_array($data['guide']['steps'] ?? null) ? $data['guide']['steps'] : [] as $step) {
		if (!is_array($step)) {
			continue;
		}
		$commands = [];
		if (isset($step['commands'])) {
			if (is_array($step['commands'])) {
				$commands = array_values(array_filter(array_map('trim', $step['commands'])));
			} elseif (is_string($step['commands'])) {
				$commands = array_values(array_filter(array_map('trim', explode(',', $step['commands']))));
			}
		}
		$steps[] = [
			'title' => (string) ($step['title'] ?? ''),
			'summary' => (string) ($step['summary'] ?? ''),
			'commands' => $commands,
		];
	}
	$data['guide']['steps'] = $steps;

	$data['catalog'] = is_array($data['catalog'] ?? null) ? $data['catalog'] : [];
	$categories = [];
	foreach (is_array($data['catalog']['categories'] ?? null) ? $data['catalog']['categories'] : [] as $category) {
		if (!is_array($category)) {
			continue;
		}
		$commands = [];
		foreach (is_array($category['commands'] ?? null) ? $category['commands'] : [] as $command) {
			if (!is_array($command)) {
				continue;
			}
			$commands[] = [
				'label' => (string) ($command['label'] ?? ''),
				'usage' => (string) ($command['usage'] ?? ''),
				'description' => (string) ($command['description'] ?? ''),
			];
		}
		$categories[] = [
			'title' => (string) ($category['title'] ?? ''),
			'summary' => (string) ($category['summary'] ?? ''),
			'commands' => $commands,
		];
	}
	$data['catalog']['categories'] = $categories;

	$data['tips'] = is_array($data['tips'] ?? null) ? $data['tips'] : [];
	$data['tips']['items'] = array_values(array_map('trim', is_array($data['tips']['items'] ?? null) ? $data['tips']['items'] : []));

	$data['faq'] = is_array($data['faq'] ?? null) ? $data['faq'] : [];
	$faqItems = [];
	foreach (is_array($data['faq']['items'] ?? null) ? $data['faq']['items'] : [] as $item) {
		if (!is_array($item)) {
			continue;
		}
		$faqItems[] = [
			'question' => (string) ($item['question'] ?? ''),
			'answer' => (string) ($item['answer'] ?? ''),
		];
	}
	$data['faq']['items'] = $faqItems;

	$data['footer'] = is_array($data['footer'] ?? null) ? $data['footer'] : [];
	$footerLinks = [];
	foreach (is_array($data['footer']['links'] ?? null) ? $data['footer']['links'] : [] as $link) {
		if (!is_array($link)) {
			continue;
		}
		$footerLinks[] = [
			'label' => (string) ($link['label'] ?? ''),
			'href' => (string) ($link['href'] ?? ''),
			'target' => (string) ($link['target'] ?? ''),
			'rel' => (string) ($link['rel'] ?? ''),
		];
	}
	$data['footer']['links'] = $footerLinks;

	return $data;
}

function normalise_payload(array $data): array
{
	$languages = [];
	if (isset($data['languages']) && is_array($data['languages'])) {
		foreach ($data['languages'] as $code) {
			$normalised = normalise_language_code((string) $code);
			if ($normalised !== '' && !in_array($normalised, $languages, true)) {
				$languages[] = $normalised;
			}
		}
	}

	$translations = [];
	if (isset($data['translations']) && is_array($data['translations'])) {
		foreach ($data['translations'] as $code => $translation) {
			$normalised = normalise_language_code((string) $code);
			if ($normalised === '') {
				continue;
			}
			if (!in_array($normalised, $languages, true)) {
				$languages[] = $normalised;
			}
			$translations[$normalised] = normalise_translation(is_array($translation) ? $translation : []);
		}
	}

	if (!$translations) {
		$defaultLanguage = normalise_language_code((string) ($data['defaultLanguage'] ?? 'ro')) ?: 'ro';
		$languages = [$defaultLanguage];
		$translations[$defaultLanguage] = normalise_translation($data);
	} else {
		foreach ($languages as $code) {
			if (!isset($translations[$code])) {
				$translations[$code] = normalise_translation([]);
			}
		}
	}

	if (!$languages) {
		$languages = array_keys($translations);
	}

	if (!$languages) {
		$languages = ['ro'];
		if (!isset($translations['ro'])) {
			$translations['ro'] = normalise_translation([]);
		}
	}

	$defaultLanguage = normalise_language_code((string) ($data['defaultLanguage'] ?? ''));
	if ($defaultLanguage === '' || !in_array($defaultLanguage, $languages, true)) {
		$defaultLanguage = $languages[0];
	}

	return [
		'languages' => array_values($languages),
		'defaultLanguage' => $defaultLanguage,
		'translations' => $translations,
	];
}
