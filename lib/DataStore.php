<?php

declare(strict_types=1);

class DataStore
{
	private string $filePath;

	public function __construct(string $filePath)
	{
		$this->filePath = $filePath;
	}

	public function read(): array
	{
		if (!is_file($this->filePath)) {
			return [];
		}

		$raw = file_get_contents($this->filePath);
		if ($raw === false) {
			return [];
		}

		$decoded = json_decode($raw, true);

		return is_array($decoded) ? $decoded : [];
	}

	public function write(array $payload): bool
	{
		$directory = dirname($this->filePath);
		if (!is_dir($directory)) {
			if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
				return false;
			}
		}

		$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return false;
		}

		return (bool) file_put_contents($this->filePath, $json . PHP_EOL, LOCK_EX);
	}

	public function lastModified(string $fallback = 'Y-m-d'): string
	{
		if (!is_file($this->filePath)) {
			return date($fallback);
		}

		$timestamp = filemtime($this->filePath);
		if ($timestamp === false) {
			return date($fallback);
		}

		return date($fallback, $timestamp);
	}
}
