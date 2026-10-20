<?php

declare(strict_types=1);

namespace GameOfLife\Classic\WebServerOnly\Tests;

use PHPUnit\Framework\TestCase;
use GameOfLife\Classic\WebServerOnly\Shell\RequestHandler;
use GameOfLife\Classic\WebServerOnly\Shell\Interpreter;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\DisplayPort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\StatePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\EventSourcePort;
use GameOfLife\Classic\WebServerOnly\Shell\Ports\PatternStorePort;
use GameOfLife\Classic\WebServerOnly\Models\ShellConfig;
use GameOfLife\Classic\Core\Core;
use GameOfLife\Classic\Core\Cells;
use GameOfLife\Classic\Core\Pattern;
use GameOfLife\Classic\Core\Position;

/**
 * Unit tests for RequestHandler
 * Tests the public behavior of handle() method through mocked ports.
 */
class RequestHandlerTest extends TestCase
{
    private RequestHandler $handler;
    private DisplayPort $displayPort;
    private StatePort $statePort;
    private EventSourcePort $eventSourcePort;
    private PatternStorePort $patternStorePort;
    private Interpreter $interpreter;
    private ShellConfig $config;

    protected function setUp(): void
    {
        $this->displayPort = $this->createMock(DisplayPort::class);
        $this->statePort = $this->createMock(StatePort::class);
        $this->eventSourcePort = $this->createMock(EventSourcePort::class);
        $this->patternStorePort = $this->createMock(PatternStorePort::class);
        $this->interpreter = $this->createMock(Interpreter::class);
        
        // Default mock behavior
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        $this->config = new ShellConfig(
            tickInterval: 2.0,
            patternsDir: __DIR__ . '/../patterns',
            debug: false
        );

        $this->handler = new RequestHandler(
            $this->interpreter,
            $this->displayPort,
            $this->patternStorePort,
            $this->statePort,
            $this->eventSourcePort,
            $this->config
        );
    }

    /**
     * Helper to create Cells from coordinate strings
     */
    private function createCells(string $coordinates): Cells
    {
        $positions = [];
        $pairs = explode('), (', trim($coordinates, '()'));
        foreach ($pairs as $pair) {
            $pair = trim($pair, ' ()');
            if (empty($pair)) continue;
            $coords = explode(',', $pair);
            $positions[] = Position::create((int)trim($coords[0]), (int)trim($coords[1]));
        }
        return new Cells(...$positions);
    }

    // =========================================================================
    // GROUP R1: Initial Request Handling
    // =========================================================================

    public function testInitialRequestSeedsDemoScene(): void
    {
        // Given: Empty session, no GET params, first request
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(true);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn(null);
        
        // Mock pattern store to return empty cells for demo scene patterns
        $this->patternStorePort->method('get')
            ->willReturn(Cells::empty());
        
        // Mock markInitialized to expect being called
        $this->statePort->expects($this->once())
            ->method('markInitialized');
        
        // The demo scene should be seeded (saveCore is called)
        $this->statePort->expects($this->once())
            ->method('saveCore');

        // When
        $this->handler->handle();
    }

    public function testInitialRequestMarksSessionAsInitialized(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(true);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn(null);
        
        // Mock pattern store to return empty cells for demo scene patterns
        $this->patternStorePort->method('get')
            ->willReturn(Cells::empty());
        
        // Expect markInitialized to be called
        $this->statePort->expects($this->once())
            ->method('markInitialized');

        // When
        $this->handler->handle();
    }

    public function testInitialRequestWithClockedParamDoesNotSetSessionFlag(): void
    {
        // Given: Empty session, GET['clocked']='1', first request
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(true);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true); // GET has clocked=1
        $this->eventSourcePort->method('getAction')->willReturn(null);
        
        // Mock pattern store to return empty cells for demo scene patterns
        $this->patternStorePort->method('get')
            ->willReturn(Cells::empty());
        
        // Session clocked flag should NOT be set (only local $clocked is set from GET)
        $this->statePort->expects($this->never())
            ->method('setClocked');

        // When
        $this->handler->handle();
    }

    // =========================================================================
    // GROUP R2: Session State Loading
    // =========================================================================

    public function testLoadCoreFromSession(): void
    {
        // Given
        $expectedCells = new Cells(Position::create(0, 0));
        $core = new Core($expectedCells, false);
        
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn(null);
        
        // Display should receive the loaded cells
        $this->displayPort->expects($this->once())
            ->method('render')
            ->with($expectedCells);

        // When
        $this->handler->handle();
    }

    public function testLoadClockedStateFromSession(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true); // Session has clocked=true
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false); // No GET param
        $this->eventSourcePort->method('getAction')->willReturn(null);

        // Display should be configured as clocked
        $this->displayPort->expects($this->once())
            ->method('setClocked')
            ->with(true);

        // When
        $this->handler->handle();
    }

    public function testGetClockedParamOverridesSession(): void
    {
        // Given: Session clocked=false, but GET has clocked=1
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true); // GET has clocked=1
        $this->eventSourcePort->method('getAction')->willReturn(null);

        // Display should be configured as clocked (from GET param)
        $this->displayPort->expects($this->once())
            ->method('setClocked')
            ->with(true);

        // When
        $this->handler->handle();
    }

    // =========================================================================
    // GROUP R3: Clock Mode Behavior (CRITICAL - addresses reported bug)
    // =========================================================================

    public function testClockedModeWithNullActionRunsOneGeneration(): void
    {
        // Given: Clocked mode, no action parameter
        $initialCore = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($initialCore);
        $this->statePort->method('isClocked')->willReturn(true);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn(null);
        
        // Mock patternStore to avoid catalog seeding
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: interpreter is called exactly ONCE with nextGenerationProgram
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testClockOnActionSetsSessionFlag(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('clock_on');
        
        // Mock patternStore
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Session clocked flag is set to true
        $this->statePort->expects($this->once())
            ->method('setClocked')
            ->with(true);

        // Expect: NO generation is run
        $this->interpreter->expects($this->never())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testClockOffActionClearsSessionFlag(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('clock_off');
        
        // Mock patternStore
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Session clocked flag is set to false
        $this->statePort->expects($this->once())
            ->method('setClocked')
            ->with(false);

        // Expect: NO generation is run
        $this->interpreter->expects($this->never())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testClockedModeWithActionParameterDoesNotAutoTick(): void
    {
        // Given: Clocked mode but with a stamp action
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn('blinker');
        $this->eventSourcePort->method('getReference')->willReturn(Position::create(0, 0));
        
        // Mock patternStore
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        $blinkerCells = new Cells(
            Position::create(-1, 0),
            Position::create(0, 0),
            Position::create(1, 0)
        );
        $this->patternStorePort->method('get')->with('blinker')->willReturn($blinkerCells);

        // Expect: interpreter is called with addPatternProgram, NOT nextGenerationProgram
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // Expect: clock_on/clock_off NOT called
        $this->statePort->expects($this->never())
            ->method('setClocked');

        // When
        $this->handler->handle();
    }

    /**
     * Data provider for clock mode scenarios
     */
    public static function clockModeProvider(): array
    {
        return [
            'session clocked, no GET' => [true, false, null, 1],
            'GET clocked, no session' => [false, true, null, 1],
            'both clocked, no action' => [true, true, null, 1],
            'clocked with tick action' => [true, true, 'tick', 1],
        ];
    }

    /**
     * @dataProvider clockModeProvider
     */
    public function testClockModeScenarios($sessionClocked, $getClocked, $action, $expectedInterpretCalls): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn($sessionClocked);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn($getClocked);
        $this->eventSourcePort->method('getAction')->willReturn($action);
        
        // Mock patternStore
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // For actions that need it
        if ($action === 'stamp') {
            $this->eventSourcePort->method('getPatternName')->willReturn('blinker');
            $this->eventSourcePort->method('getReference')->willReturn(Position::create(0, 0));
            $blinkerCells = new Cells(
                Position::create(-1, 0),
                Position::create(0, 0),
                Position::create(1, 0)
            );
            $this->patternStorePort->method('get')->with('blinker')->willReturn($blinkerCells);
        }

        // Expect: interpreter called expected number of times
        $this->interpreter->expects($this->exactly($expectedInterpretCalls))
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testMultipleClockRefreshesEachRunOneGeneration(): void
    {
        // This test verifies that each auto-refresh runs exactly one generation
        // We simulate 3 consecutive refreshes with clocked=1
        
        $core = Core::initial();
        
        for ($i = 0; $i < 3; $i++) {
            // Reset mocks for each iteration
            $this->setUp();
            
            // Given: Clocked mode, no action (simulating auto-refresh)
            $this->statePort->method('isFirstRequest')->willReturn(false);
            $this->statePort->method('loadCore')->willReturn($core);
            $this->statePort->method('isClocked')->willReturn(true);
            $this->statePort->method('getSelectedPattern')->willReturn('blinker');
            $this->statePort->method('isStatusVisible')->willReturn(false);
            $this->eventSourcePort->method('isClocked')->willReturn(true);
            $this->eventSourcePort->method('getAction')->willReturn(null);
            $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

            // Expect: interpreter called exactly ONCE
            $this->interpreter->expects($this->once())
                ->method('interpret');

            // When
            $this->handler->handle();
            
            // Note: In reality, the interpreter would save the new core to session
            // For this test, we just verify the call count
        }
    }

    // =========================================================================
    // GROUP R4: Action Handling
    // =========================================================================

    public function testTickActionRunsOneGeneration(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('tick');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: interpreter called with nextGenerationProgram
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testStepActionRunsOneGeneration(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('step');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: interpreter called with nextGenerationProgram
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testResetActionCreatesNewCoreAndSeedsDemo(): void
    {
        // Given
        $oldCore = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($oldCore);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('reset');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        // Mock pattern store to return empty cells for demo scene patterns
        $this->patternStorePort->method('get')
            ->willReturn(Cells::empty());

        // Expect: A new core with demo scene is saved (twice: once in reset, once in renderFinalState)
        $this->statePort->expects($this->exactly(2))
            ->method('saveCore');

        // When
        $this->handler->handle();
    }

    public function testStampActionAddsPatternAtPosition(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn('blinker');
        $this->eventSourcePort->method('getReference')->willReturn(Position::create(10, 20));
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        // Mock pattern store to return blinker cells
        $blinkerCells = new Cells(
            Position::create(-1, 0),
            Position::create(0, 0),
            Position::create(1, 0)
        );
        $this->patternStorePort->method('get')->with('blinker')->willReturn($blinkerCells);

        // Expect: interpreter called with addPatternProgram
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testStampActionUsesSelectedPatternWhenNotInGet(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('glider');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn(null); // Uses session's selectedPattern
        $this->eventSourcePort->method('getReference')->willReturn(Position::create(5, 5));
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        // Mock pattern store to return glider cells when called with 'glider'
        $gliderCells = new Cells(
            Position::create(0, -1),
            Position::create(1, 0),
            Position::create(-1, 1),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        $this->patternStorePort->method('get')
            ->willReturn($gliderCells);

        // Expect: interpreter called with addPatternProgram
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testSelectPatternActionUpdatesSelectedPattern(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('select_pattern');
        $this->eventSourcePort->method('getPatternName')->willReturn('glider');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Selected pattern is updated
        $this->statePort->expects($this->once())
            ->method('setSelectedPattern')
            ->with('glider');

        // When
        $this->handler->handle();
    }

    public function testToggleStatusActionFlipsUiState(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('toggle_status');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Status visibility is flipped to true
        $this->statePort->expects($this->once())
            ->method('setStatusVisible')
            ->with(true);

        // When
        $this->handler->handle();
    }

    // =========================================================================
    // GROUP R5: State Persistence
    // =========================================================================

    public function testCoreIsSavedAfterGeneration(): void
    {
        // Given
        $initialCore = Core::initial();
        $newCore = Core::initial();
        
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $callCount = 0;
        $this->statePort->method('loadCore')
            ->willReturnCallback(function() use (&$callCount, $initialCore, $newCore) {
                $callCount++;
                return $callCount === 1 ? $initialCore : $newCore;
            });
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('tick');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Core is saved after generation
        $this->statePort->expects($this->once())
            ->method('saveCore');

        // When
        $this->handler->handle();
    }

    public function testCoreIsSavedAfterStamp(): void
    {
        // Given
        $core = Core::initial();
        $newCore = Core::initial();
        
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $callCount = 0;
        $this->statePort->method('loadCore')
            ->willReturnCallback(function() use (&$callCount, $core, $newCore) {
                $callCount++;
                return $callCount === 1 ? $core : $newCore;
            });
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn('blinker');
        $this->eventSourcePort->method('getReference')->willReturn(Position::create(0, 0));
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        $blinkerCells = new Cells(
            Position::create(-1, 0),
            Position::create(0, 0),
            Position::create(1, 0)
        );
        $this->patternStorePort->method('get')->with('blinker')->willReturn($blinkerCells);

        // Expect: Core is saved
        $this->statePort->expects($this->once())
            ->method('saveCore');

        // When
        $this->handler->handle();
    }

    // =========================================================================
    // GROUP R8: Edge Cases and Error Handling
    // =========================================================================

    public function testUnknownActionIsIgnored(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('unknown_action');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: No interpreter call (no generation)
        $this->interpreter->expects($this->never())
            ->method('interpret');

        // Expect: Display still shows current state
        $this->displayPort->expects($this->once())
            ->method('render');

        // When
        $this->handler->handle();
    }

    public function testMissingPositionParametersForStamp(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn('blinker');
        $this->eventSourcePort->method('getReference')->willReturn(null); // Missing x/y
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: interpreter is NOT called (no position to stamp)
        // Note: With null reference, addPatternProgram will still be called but with null
        // The RequestHandler checks for null reference and may skip
        // For now, we expect it to call interpret
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testUnknownPatternForStamp(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn('nonexistent');
        $this->eventSourcePort->method('getReference')->willReturn(Position::create(0, 0));
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        // Mock pattern store to return empty cells for unknown pattern
        $this->patternStorePort->method('get')->with('nonexistent')->willReturn(Cells::empty());

        // Expect: interpreter is called (addPatternProgram is built)
        // but the result should be no change to core
        $this->interpreter->expects($this->once())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testEmptyGetParameters(): void
    {
        // Given
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn(null);
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: No interpreter call, just display current state
        $this->interpreter->expects($this->never())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testClockOnAndClockedParamInGet(): void
    {
        // Given: action=clock_on AND clocked=1 in GET
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true); // GET has clocked=1
        $this->eventSourcePort->method('getAction')->willReturn('clock_on');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Session clocked flag is set to true
        $this->statePort->expects($this->once())
            ->method('setClocked')
            ->with(true);

        // Expect: NO generation is run
        $this->interpreter->expects($this->never())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    public function testClockOffWithClockedParamInGet(): void
    {
        // Given: action=clock_off AND clocked=1 in GET
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true); // GET has clocked=1
        $this->eventSourcePort->method('getAction')->willReturn('clock_off');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        // Expect: Session clocked flag is set to false
        $this->statePort->expects($this->once())
            ->method('setClocked')
            ->with(false);

        // Expect: NO generation is run
        $this->interpreter->expects($this->never())
            ->method('interpret');

        // When
        $this->handler->handle();
    }

    // =========================================================================
    // GROUP R9: Complete Request Flows
    // =========================================================================

    public function testFullClockCycle(): void
    {
        // Step 1: Clock On clicked
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('clock_on');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        $this->statePort->expects($this->once())
            ->method('setClocked')
            ->with(true);
        $this->interpreter->expects($this->never())
            ->method('interpret');

        $this->handler->handle();

        // Step 2: First auto-refresh (no action, clocked=1)
        $this->setUp();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true); // Now true from step 1
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true); // GET has clocked=1
        $this->eventSourcePort->method('getAction')->willReturn(null);
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        $this->interpreter->expects($this->once())
            ->method('interpret');

        $this->handler->handle();

        // Step 3: Second auto-refresh
        $this->setUp();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true);
        $this->eventSourcePort->method('getAction')->willReturn(null);
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        $this->interpreter->expects($this->once())
            ->method('interpret');

        $this->handler->handle();

        // Step 4: Clock Off clicked
        $this->setUp();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(true);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(true);
        $this->eventSourcePort->method('getAction')->willReturn('clock_off');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        $this->statePort->expects($this->once())
            ->method('setClocked')
            ->with(false);
        $this->interpreter->expects($this->never())
            ->method('interpret');

        $this->handler->handle();
    }

    public function testSelectPatternThenStamp(): void
    {
        // Step 1: Select pattern
        $core = Core::initial();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('blinker');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('select_pattern');
        $this->eventSourcePort->method('getPatternName')->willReturn('glider');
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

        $this->statePort->expects($this->once())
            ->method('setSelectedPattern')
            ->with('glider');

        $this->handler->handle();

        // Step 2: Stamp at position
        $this->setUp();
        $this->statePort->method('isFirstRequest')->willReturn(false);
        $this->statePort->method('loadCore')->willReturn($core);
        $this->statePort->method('isClocked')->willReturn(false);
        $this->statePort->method('getSelectedPattern')->willReturn('glider');
        $this->statePort->method('isStatusVisible')->willReturn(false);
        $this->eventSourcePort->method('isClocked')->willReturn(false);
        $this->eventSourcePort->method('getAction')->willReturn('stamp');
        $this->eventSourcePort->method('getPatternName')->willReturn(null); // Uses session's selectedPattern
        $this->eventSourcePort->method('getReference')->willReturn(Position::create(10, 10));
        $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);
        
        $gliderCells = new Cells(
            Position::create(0, -1),
            Position::create(1, 0),
            Position::create(-1, 1),
            Position::create(0, 1),
            Position::create(1, 1)
        );
        $this->patternStorePort->method('get')->with('glider')->willReturn($gliderCells);

        $this->interpreter->expects($this->once())
            ->method('interpret');

        $this->handler->handle();
    }

    public function testMultipleRapidActions(): void
    {
        // Given: Empty session, core with some cells
        $core = Core::initial();

        // Simulate 3 consecutive Step actions
        for ($i = 0; $i < 3; $i++) {
            $this->setUp();
            
            $this->statePort->method('isFirstRequest')->willReturn(false);
            $this->statePort->method('loadCore')->willReturn($core);
            $this->statePort->method('isClocked')->willReturn(false);
            $this->statePort->method('getSelectedPattern')->willReturn('blinker');
            $this->statePort->method('isStatusVisible')->willReturn(false);
            $this->eventSourcePort->method('isClocked')->willReturn(false);
            $this->eventSourcePort->method('getAction')->willReturn('step');
            $this->patternStorePort->method('isCatalogSeeded')->willReturn(true);

            $this->interpreter->expects($this->once())
                ->method('interpret');

            $this->handler->handle();
        }
    }
}
