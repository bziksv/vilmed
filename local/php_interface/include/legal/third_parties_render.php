<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

require_once __DIR__ . '/legal_helpers.php';

function vilmedLegalThirdPartiesData(): array
{
    static $data = null;
    if ($data === null) {
        $data = include __DIR__ . '/third_parties_data.php';
    }

    return $data;
}

function vilmedLegalRenderThirdPartyPolicyLine(array $service): string
{
    $parts = [legal_var($service['name'])];
    if (!empty($service['inn'])) {
        $parts[0] .= ' (ИНН ' . legal_var($service['inn']) . ')';
    }

    $line = $parts[0] . ' — ';
    $line .= '<a href="' . legal_h($service['url']) . '" target="_blank" rel="noopener">'
        . legal_var($service['link_label']) . '</a>';
    if (!empty($service['suffix'])) {
        $line .= ', ' . legal_var($service['suffix']);
    }

    return $line;
}

function vilmedLegalRenderThirdPartyConsentLine(array $service): string
{
    $parts = [legal_var($service['name'])];
    if (!empty($service['inn'])) {
        $parts[0] .= ' (ИНН ' . legal_var($service['inn']) . ')';
    }

    $description = $service['link_label'];
    if (!empty($service['suffix'])) {
        $description .= ', ' . $service['suffix'];
    }

    return $parts[0] . ' (' . legal_var($description) . ') — '
        . '<a href="' . legal_h($service['url']) . '" target="_blank" rel="noopener">'
        . legal_var($service['url']) . '</a>';
}

function vilmedLegalRenderThirdPartyServiceName(array $service): string
{
    $name = legal_var($service['name']);
    if (!empty($service['inn'])) {
        $name .= ' (ИНН ' . legal_var($service['inn']) . ')';
    }

    return $name;
}

function vilmedLegalRenderThirdPartyRecommendationLine(array $block, ?array $service = null): string
{
    $links = [];
    foreach ($block['urls'] as $url) {
        $links[] = '<a href="' . legal_h($url) . '" target="_blank" rel="noopener">'
            . legal_var($url) . '</a>';
    }

    $line = implode(', ', $links) . ' — ' . legal_var($block['text']);
    if ($service !== null) {
        $line = vilmedLegalRenderThirdPartyServiceName($service) . ' — ' . $line;
    }

    return $line;
}

function vilmedLegalRenderThirdPartyUrlListItems(): string
{
    $html = '';
    foreach (vilmedLegalThirdPartiesData()['services'] as $service) {
        if (empty($service['recommendation'])) {
            continue;
        }
        foreach ($service['recommendation'] as $block) {
            $html .= '<li>' . vilmedLegalRenderThirdPartyRecommendationLine($block, $service) . ";</li>\n        ";
        }
    }

    return $html;
}
