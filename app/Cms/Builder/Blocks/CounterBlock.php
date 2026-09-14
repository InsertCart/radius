<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * A number that counts up when it scrolls into view.
 */
class CounterBlock extends Block
{
    public static function type(): string
    {
        return 'counter';
    }

    public static function name(): string
    {
        return 'Counter';
    }

    public static function icon(): string
    {
        return 'counter';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 35;
    }

    public static function keywords(): array
    {
        return ['number', 'stat', 'statistic'];
    }

    public static function controls(): array
    {
        return [
            Control::number('start', 'Starting number')->default(0),
            Control::number('end', 'Target number')->default(500),
            Control::number('duration', 'Duration (ms)')->default(1800),
            Control::text('prefix', 'Prefix')->placeholder('$'),
            Control::text('suffix', 'Suffix')->placeholder('+'),
            Control::text('title', 'Label')->default('Happy customers'),
            Control::toggle('separator', 'Use thousands separator')->default(true),

            Control::choose('align', 'Alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('center')
                ->selector('{{WRAPPER}} .cb-counter', 'text-align'),

            Control::color('number_color', 'Number colour')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-counter__number', 'color'),

            Control::typography('number_typography', 'Number typography')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-counter__number', 'typography'),

            Control::color('title_color', 'Label colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-counter__title', 'color'),
        ];
    }
}
