<?php
/**
 * Aleltu Farm EIFMS - minimal SVG chart generator.
 *
 * Deliberately dependency-free: no Chart.js, no CDN, nothing that needs
 * internet access to render. Every function returns a raw <svg> string
 * that can be echoed directly into a page. This matches the same
 * offline-first constraint as the rest of this app.
 */

function svg_bar_chart(array $labels, array $values, string $unit = '', int $width = 560, int $height = 220): string
{
    $count = count($values);
    if ($count === 0) {
        return '<p class="text-muted">No data yet.</p>';
    }

    $paddingLeft = 40;
    $paddingBottom = 30;
    $paddingTop = 10;
    $chartWidth = $width - $paddingLeft - 10;
    $chartHeight = $height - $paddingBottom - $paddingTop;

    $max = max($values);
    $max = $max > 0 ? $max : 1;
    $barGap = 8;
    $barWidth = ($chartWidth - ($barGap * ($count - 1))) / $count;
    $barWidth = max($barWidth, 4);

    $svg = "<svg viewBox=\"0 0 {$width} {$height}\" xmlns=\"http://www.w3.org/2000/svg\" style=\"width:100%;height:auto\">";

    // Y-axis gridlines (4 steps)
    for ($i = 0; $i <= 4; $i++) {
        $y = $paddingTop + $chartHeight - ($chartHeight * $i / 4);
        $val = round($max * $i / 4, 1);
        $svg .= "<line x1=\"{$paddingLeft}\" y1=\"{$y}\" x2=\"" . ($width - 10) . "\" y2=\"{$y}\" stroke=\"var(--border, #dfe5dd)\" stroke-width=\"1\" />";
        $svg .= "<text x=\"" . ($paddingLeft - 6) . "\" y=\"" . ($y + 4) . "\" font-size=\"10\" fill=\"var(--muted, #6b7568)\" text-anchor=\"end\">{$val}</text>";
    }

    foreach ($values as $i => $v) {
        $barHeight = $max > 0 ? ($v / $max) * $chartHeight : 0;
        $x = $paddingLeft + $i * ($barWidth + $barGap);
        $y = $paddingTop + $chartHeight - $barHeight;
        $label = htmlspecialchars((string) ($labels[$i] ?? ''), ENT_QUOTES, 'UTF-8');
        $displayVal = htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $svg .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$barWidth}\" height=\"{$barHeight}\" fill=\"var(--primary, #2f6e3f)\" rx=\"2\">";
        $svg .= "<title>{$label}: {$displayVal} {$unit}</title></rect>";
        $svg .= "<text x=\"" . ($x + $barWidth / 2) . "\" y=\"" . ($height - 10) . "\" font-size=\"10\" fill=\"var(--muted, #6b7568)\" text-anchor=\"middle\">{$label}</text>";
    }

    $svg .= '</svg>';
    return $svg;
}

/**
 * Two-series bar chart (e.g. inflow vs outflow, dispatched vs delivered)
 * — bars for each series sit side by side per label.
 */
function svg_grouped_bar_chart(array $labels, array $seriesA, array $seriesB, string $labelA, string $labelB, int $width = 560, int $height = 240): string
{
    $count = count($labels);
    if ($count === 0) {
        return '<p class="text-muted">No data yet.</p>';
    }

    $paddingLeft = 45;
    $paddingBottom = 40;
    $paddingTop = 20;
    $chartWidth = $width - $paddingLeft - 10;
    $chartHeight = $height - $paddingBottom - $paddingTop;

    $max = max(array_merge($seriesA, $seriesB, [1]));
    $groupGap = 14;
    $groupWidth = ($chartWidth - ($groupGap * ($count - 1))) / $count;
    $barWidth = max(($groupWidth - 4) / 2, 3);

    $svg = "<svg viewBox=\"0 0 {$width} {$height}\" xmlns=\"http://www.w3.org/2000/svg\" style=\"width:100%;height:auto\">";

    for ($i = 0; $i <= 4; $i++) {
        $y = $paddingTop + $chartHeight - ($chartHeight * $i / 4);
        $val = round($max * $i / 4);
        $svg .= "<line x1=\"{$paddingLeft}\" y1=\"{$y}\" x2=\"" . ($width - 10) . "\" y2=\"{$y}\" stroke=\"var(--border, #dfe5dd)\" stroke-width=\"1\" />";
        $svg .= "<text x=\"" . ($paddingLeft - 6) . "\" y=\"" . ($y + 4) . "\" font-size=\"10\" fill=\"var(--muted, #6b7568)\" text-anchor=\"end\">{$val}</text>";
    }

    foreach ($labels as $i => $label) {
        $x = $paddingLeft + $i * ($groupWidth + $groupGap);
        $vA = $seriesA[$i] ?? 0;
        $vB = $seriesB[$i] ?? 0;
        $hA = $max > 0 ? ($vA / $max) * $chartHeight : 0;
        $hB = $max > 0 ? ($vB / $max) * $chartHeight : 0;
        $labelText = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');

        $svg .= "<rect x=\"{$x}\" y=\"" . ($paddingTop + $chartHeight - $hA) . "\" width=\"{$barWidth}\" height=\"{$hA}\" fill=\"var(--primary, #2f6e3f)\"><title>{$labelA}: {$vA}</title></rect>";
        $svg .= "<rect x=\"" . ($x + $barWidth + 4) . "\" y=\"" . ($paddingTop + $chartHeight - $hB) . "\" width=\"{$barWidth}\" height=\"{$hB}\" fill=\"var(--danger, #b3261e)\"><title>{$labelB}: {$vB}</title></rect>";
        $svg .= "<text x=\"" . ($x + $groupWidth / 2) . "\" y=\"" . ($height - 22) . "\" font-size=\"10\" fill=\"var(--muted, #6b7568)\" text-anchor=\"middle\">{$labelText}</text>";
    }

    // Legend
    $svg .= "<rect x=\"{$paddingLeft}\" y=\"" . ($height - 14) . "\" width=\"10\" height=\"10\" fill=\"var(--primary, #2f6e3f)\" />";
    $svg .= "<text x=\"" . ($paddingLeft + 14) . "\" y=\"" . ($height - 5) . "\" font-size=\"10\" fill=\"var(--text, #24291f)\">" . htmlspecialchars($labelA, ENT_QUOTES, 'UTF-8') . "</text>";
    $legendX2 = $paddingLeft + 100;
    $svg .= "<rect x=\"{$legendX2}\" y=\"" . ($height - 14) . "\" width=\"10\" height=\"10\" fill=\"var(--danger, #b3261e)\" />";
    $svg .= "<text x=\"" . ($legendX2 + 14) . "\" y=\"" . ($height - 5) . "\" font-size=\"10\" fill=\"var(--text, #24291f)\">" . htmlspecialchars($labelB, ENT_QUOTES, 'UTF-8') . "</text>";

    $svg .= '</svg>';
    return $svg;
}

function svg_line_chart(array $labels, array $values, string $unit = '', int $width = 560, int $height = 220): string
{
    $count = count($values);
    if ($count === 0) {
        return '<p class="text-muted">No data yet.</p>';
    }
    if ($count === 1) {
        return svg_bar_chart($labels, $values, $unit, $width, $height);
    }

    $paddingLeft = 40;
    $paddingBottom = 30;
    $paddingTop = 10;
    $chartWidth = $width - $paddingLeft - 10;
    $chartHeight = $height - $paddingBottom - $paddingTop;

    $max = max($values);
    $max = $max > 0 ? $max : 1;
    $stepX = $chartWidth / ($count - 1);

    $points = [];
    foreach ($values as $i => $v) {
        $x = $paddingLeft + $i * $stepX;
        $y = $paddingTop + $chartHeight - (($v / $max) * $chartHeight);
        $points[] = [$x, $y];
    }

    $svg = "<svg viewBox=\"0 0 {$width} {$height}\" xmlns=\"http://www.w3.org/2000/svg\" style=\"width:100%;height:auto\">";

    for ($i = 0; $i <= 4; $i++) {
        $y = $paddingTop + $chartHeight - ($chartHeight * $i / 4);
        $val = round($max * $i / 4, 1);
        $svg .= "<line x1=\"{$paddingLeft}\" y1=\"{$y}\" x2=\"" . ($width - 10) . "\" y2=\"{$y}\" stroke=\"var(--border, #dfe5dd)\" stroke-width=\"1\" />";
        $svg .= "<text x=\"" . ($paddingLeft - 6) . "\" y=\"" . ($y + 4) . "\" font-size=\"10\" fill=\"var(--muted, #6b7568)\" text-anchor=\"end\">{$val}</text>";
    }

    $pathD = 'M ' . implode(' L ', array_map(fn ($p) => "{$p[0]},{$p[1]}", $points));
    $svg .= "<path d=\"{$pathD}\" fill=\"none\" stroke=\"var(--primary, #2f6e3f)\" stroke-width=\"2\" />";

    foreach ($points as $i => $p) {
        $label = htmlspecialchars((string) ($labels[$i] ?? ''), ENT_QUOTES, 'UTF-8');
        $displayVal = htmlspecialchars((string) $values[$i], ENT_QUOTES, 'UTF-8');
        $svg .= "<circle cx=\"{$p[0]}\" cy=\"{$p[1]}\" r=\"3\" fill=\"var(--primary, #2f6e3f)\"><title>{$label}: {$displayVal} {$unit}</title></circle>";
        if ($i % max(1, (int) ceil($count / 8)) === 0 || $i === $count - 1) {
            $svg .= "<text x=\"{$p[0]}\" y=\"" . ($height - 10) . "\" font-size=\"10\" fill=\"var(--muted, #6b7568)\" text-anchor=\"middle\">{$label}</text>";
        }
    }

    $svg .= '</svg>';
    return $svg;
}
