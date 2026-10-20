<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Shell;

use GameOfLife\Classic\Core\Core;
use GameOfLife\Classic\Core\CoreOperations;
use GameOfLife\Classic\Core\Cells;
use GameOfLife\Classic\Core\Position;
use GameOfLife\Classic\Core\Pattern;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\DisplayPort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\PatternStorePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\StatePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\EventSourcePort;
use GameOfLife\Classic\WebServerOnly\Models\ShellConfig;

/**
 * Main request handler for the WebServerOnly shell.
 * Processes HTTP requests, dispatches actions, and returns HTML output.
 */
final class RequestHandler
{
    private PatternStorePort $patternStore;
    private DisplayPort $display;
    private StatePort $state;
    private EventSourcePort $eventSource;
    private Interpreter $interpreter;
    private ShellConfig $config;

    public function __construct(
        Interpreter $interpreter,
        DisplayPort $display,
        PatternStorePort $patternStore,
        StatePort $state,
        EventSourcePort $eventSource,
        ShellConfig $config
    ) {
        $this->interpreter = $interpreter;
        $this->display = $display;
        $this->patternStore = $patternStore;
        $this->state = $state;
        $this->eventSource = $eventSource;
        $this->config = $config;
    }

    /**
     * Handle an HTTP request and return the HTML output.
     *
     * @return string Complete HTML page
     */
    public function handle(): string
    {
        // 1. Load core and UI state from session
        $core = $this->state->loadCore();
        $clocked = $this->state->isClocked();
        $selectedPattern = $this->state->getSelectedPattern();

        // 2. Seed demo scene on first request
        if ($this->state->isFirstRequest()) {
            $core = $this->seedDemoScene($core);
            $this->state->markInitialized();
        }

        // 3. Ensure pattern catalog is seeded
        if (!$this->patternStore->isCatalogSeeded()) {
            $this->patternStore->seedCatalog();
        }

        // 4. Get action from event source
        $action = $this->eventSource->getAction();

        // 5. Handle UI state actions (don't build programs)
        if ($action === 'select_pattern') {
            $pattern = $this->eventSource->getPatternName() ?? $selectedPattern;
            $this->state->setSelectedPattern($pattern);
            $this->state->saveCore($core);
            return $this->renderFinalState($core, $clocked, $pattern);
        }

        if ($action === 'clock_on') {
            $this->state->setClocked(true);
            $clocked = true;
        } elseif ($action === 'clock_off') {
            $this->state->setClocked(false);
            $clocked = false;
        }

        // 6. Build and interpret program based on action
        if ($action === 'tick' || ($clocked && $action === null)) {
            $program = CoreOperations::nextGenerationProgram($core);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore();
        } elseif ($action === 'stamp') {
            $patternName = $this->eventSource->getPatternName() ?? $selectedPattern;
            $reference = $this->eventSource->getReference() ?? Position::create(0, 0);
            $program = CoreOperations::addPatternProgram($core, $patternName, $reference);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore();
        } elseif ($action === 'toggle_status') {
            $program = CoreOperations::switchStatusVisibilityProgram($core);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore();
        } elseif ($action === 'step') {
            // step is the same as tick
            $program = CoreOperations::nextGenerationProgram($core);
            $this->interpreter->interpret($program);
            $core = $this->state->loadCore();
        }

        // 7. Save final core and render
        return $this->renderFinalState($core, $clocked, $selectedPattern);
    }

    /**
     * Seed the demo scene with the 13 stamps from Appendix C.
     *
     * @param Core $core Initial core
     * @return Core Core with demo scene applied
     */
    private function seedDemoScene(Core $core): Core
    {
        // The 13 stamps from Appendix C
        $stamps = [
            ['pattern' => 'hwss', 'x' => 33, 'y' => 20],
            ['pattern' => 'rpentomino', 'x' => 35, 'y' => 10],
            ['pattern' => 'pentadecathlon', 'x' => 25, 'y' => 30],
            ['pattern' => 'blinker', 'x' => 25, 'y' => 10],
            ['pattern' => 'pentadecathlon', 'x' => 20, 'y' => 15],
            ['pattern' => 'mwss', 'x' => 40, 'y' => 15],
            ['pattern' => 'glider', 'x' => 13, 'y' => 13],
            ['pattern' => 'toad', 'x' => 30, 'y' => 15],
            ['pattern' => 'gosperGun', 'x' => 12, 'y' => 10],
            ['pattern' => 'pulsar', 'x' => 13, 'y' => 24],
            ['pattern' => 'acorn', 'x' => 25, 'y' => 25],
            ['pattern' => 'beacon', 'x' => 25, 'y' => 20],
            ['pattern' => 'pulsar', 'x' => 36, 'y' => 30],
        ];

        foreach ($stamps as $stamp) {
            $patternCells = $this->patternStore->get($stamp['pattern']);
            if ($patternCells->count() === 0) {
                continue;
            }
            $reference = new Position($stamp['x'], $stamp['y']);
            $translated = Pattern::translate($patternCells, $reference);
            $core = $core->withCells($core->cellsOf()->union($translated));
        }

        return $core;
    }

    /**
     * Render the final state with proper display configuration.
     *
     * @param Core $core The final core state
     * @param bool $clocked Whether clocked mode is active
     * @param string $selectedPattern The selected pattern name
     * @return string Complete HTML page
     */
    private function renderFinalState(Core $core, bool $clocked, string $selectedPattern): string
    {
        // Save core to state
        $this->state->saveCore($core);

        // Configure display
        $this->display->setClocked($clocked);
        $this->display->setSelectedPattern($selectedPattern);
        $this->display->render($core->cellsOf());

        if ($core->statusVisibleOf()) {
            $this->display->show(Pattern::statusMessage($core->cellsOf()));
        } else {
            $this->display->hide();
        }

        return $this->display->getOutput();
    }
}
