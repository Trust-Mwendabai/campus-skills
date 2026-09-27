<?php
// Small helpers shared by the administration pages.

/**
 * Render a table. $columns = [ ['Label', callable(row): string html], ... ]
 * Callables must escape their own output.
 */
function render_table(array $columns, array $rows, string $empty = 'No records found.'): string
{
    if (!$rows) {
        return '<div class="empty">' . e($empty) . '</div>';
    }
    $out = '<div class="table-wrap"><table class="table"><thead><tr>';
    foreach ($columns as [$label]) {
        $out .= '<th>' . e($label) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $out .= '<tr>';
        foreach ($columns as [, $render]) {
            $out .= '<td>' . $render($row) . '</td>';
        }
        $out .= '</tr>';
    }
    return $out . '</tbody></table></div>';
}

function user_cell(string $name, ?string $sub, int $id, ?string $href = null): string
{
    $link = $href ?? 'users.php?id=' . $id;
    return '<div class="cell-user">' . avatar(['id' => $id, 'name' => $name]) . '<div><a href="' . e($link) . '"><b>' . e($name) . '</b></a>'
         . ($sub !== null ? '<small>' . e($sub) . '</small>' : '') . '</div></div>';
}

/** Inline POST button form for row actions. */
function action_form(string $do, int $id, string $label, string $class = 'btn-outline', ?string $confirm = null, array $extra = []): string
{
    $hidden = '';
    foreach ($extra as $k => $v) {
        $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    return '<form method="post" class="inline-form"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
         . csrf_field() . '<input type="hidden" name="do" value="' . e($do) . '"><input type="hidden" name="id" value="' . $id . '">' . $hidden
         . '<button class="btn btn-sm ' . e($class) . '">' . e($label) . '</button></form>';
}

/** Big-number stat card. */
function stat_card(string $label, string|int|float $value, string $ico, string $tone = ''): string
{
    return '<div class="panel stat"><span class="stat-icon ' . e($tone) . '">' . icon($ico, 22) . '</span>'
         . '<div><div class="stat-num">' . e((string) $value) . '</div><div class="stat-label">' . e($label) . '</div></div></div>';
}
