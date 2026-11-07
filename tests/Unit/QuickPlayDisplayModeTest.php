<?php

namespace Tests\Unit;

use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use PHPUnit\Framework\TestCase;

class QuickPlayDisplayModeTest extends TestCase
{
    public function test_historical_modes_have_neutral_labels_and_fullscreen_remains_available(): void
    {
        foreach ([QuickPlayDisplayMode::Advertising, QuickPlayDisplayMode::Business] as $mode) {
            $this->assertSame('Envío histórico', $mode->label());
            $this->assertStringContainsString('No es posible verificar', $mode->description());
        }

        $this->assertSame('Pantalla completa', QuickPlayDisplayMode::Fullscreen->label());
        $this->assertStringContainsString('pantalla completa', QuickPlayDisplayMode::Fullscreen->description());
    }
}
