<?php

const SNIPPET_TAG_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tags';

function normalizeSnippetTag($rawTagName) {
    $tagName = trim((string)($rawTagName ?? 'main'));
    if ($tagName === '') {
        $tagName = 'main';
    }

    $tagName = str_replace(':', '_', $tagName);
    if (str_ends_with($tagName, '.tex')) {
        $tagName = substr($tagName, 0, -4);
    }

    if (!preg_match('/\A[A-Za-z0-9_]+\z/', $tagName)) {
        return null;
    }

    return $tagName;
}

function snippetFilePath($tagName) {
    $tagName = normalizeSnippetTag($tagName);
    if ($tagName === null) {
        return null;
    }

    $basePath = realpath(SNIPPET_TAG_DIR);
    $filePath = realpath(SNIPPET_TAG_DIR . DIRECTORY_SEPARATOR . $tagName . '.tex');

    if ($basePath === false || $filePath === false) {
        return null;
    }

    if (!str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $filePath;
}

function snippetUrlTag($label) {
    return str_replace(':', '_', (string)$label);
}

function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

