<?php

require_once __DIR__ . "/code/snippetHelpers.php";

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$path = urldecode((string) $path);
$root = realpath(__DIR__);
$requestedFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($path, "/\\"));

if ($root !== false && $requestedFile !== false && is_file($requestedFile) && str_starts_with($requestedFile, $root . DIRECTORY_SEPARATOR)) {
    return false;
}

$tag = trim($path, "/\\");
if ($tag === "") {
    $tag = "main";
}

if (normalizeSnippetTag($tag) !== null && snippetFilePath($tag) !== null) {
    $_GET["tag"] = $tag;
    require __DIR__ . "/index.php";
    return true;
}

return false;
