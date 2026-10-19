//! Main application logic and event loop for v2 Multi-Breed

use anyhow::Result;
use crossterm::event::{KeyCode, MouseButton, MouseEventKind};
use gol_multibreed_core::{
    add_pattern_program, define_current_pattern_program, next_generation_program, 
    switch_status_visibility_program, Breed, Cell, Core, Position
};

use crate::config::HELP_TEXT;
use crate::demo::seed_demo_scene;
use crate::events::{Event, EventSource};
use crate::interpreter::{execute_program, App};
use crate::store::PatternStore;
use crate::ui::{screen_to_world, Display};

/// Main application struct that coordinates between components
pub struct Application {
    app: App,
    display: Display,
    store: PatternStore,
    events: EventSource,
}

impl Application {
    /// Creates a new Application instance
    pub fn new(database_path: &str) -> Result<Self> {
        // Initialize display
        let mut display = Display::new()?;

        // Initialize pattern store
        let store = PatternStore::new(database_path)?;

        // Seed the catalog with breed 0 patterns
        store.seed_catalog()?;

        // Get pattern names
        let pattern_names = store.get_pattern_names()?;

        // Initialize app state
        let mut app = App::new(pattern_names);

        // v2: Initialize with status visible (starts visible in v2)
        app.current_core = app.current_core.with_status_visible(true);
        app.status_visible = true;
        
        // v2: Initialize selected_breed to 0
        app.selected_breed = Breed::new(0);

        // Seed demo scene with breed-aware stamping and get the cells
        let initial_core = Core::initial().with_status_visible(true);
        let (seeded_core, demo_cells) = seed_demo_scene(initial_core, &store)?;
        app.current_core = seeded_core;
        app.current_cells = demo_cells;

        // Initialize event source
        let events = EventSource::new()?;

        // Get initial terminal size
        let (width, height) = crossterm::terminal::size()?;
        display.handle_resize(width, height)?;
        
        // Center the viewport on the demo scene (using known coordinates)
        display.center_on_demo_scene();

        Ok(Self {
            app,
            display,
            store,
            events,
        })
    }

    /// Runs the main event loop
    pub fn run(&mut self) -> Result<()> {
        // Initial render
        self.update_display()?;

        // Main event loop
        while self.app.running {
            match self.events.next_event()? {
                Event::Tick => {
                    if self.app.is_clock_mode {
                        let program = next_generation_program(&self.app.current_core);
                        execute_program(program, &mut self.app, &mut self.display, &self.store)?;
                        self.update_display()?;
                    }
                }
                Event::Key(key) => {
                    self.handle_key_event(key)?;
                    self.update_display()?;
                }
                Event::Mouse(mouse) => {
                    self.handle_mouse_event(mouse)?;
                    self.update_display()?;
                }
                Event::Resize(width, height) => {
                    self.display.handle_resize(width, height)?;
                    self.update_display()?;
                }
                Event::Quit => {
                    self.app.running = false;
                }
            }
        }

        Ok(())
    }

    /// Helper to update the display with current state
    fn update_display(&mut self) -> Result<()> {
        let pattern_name = if !self.app.pattern_names.is_empty() {
            self.app.pattern_names[self.app.selected_pattern_idx].clone()
        } else {
            "none".to_string()
        };
        let mode = if self.app.is_clock_mode { "clock" } else { "step" };
        
        // Show cell count in status bar only if status is visible
        let status_text = if self.app.status_visible {
            Some(gol_multibreed_core::status_message(&self.app.current_cells))
        } else {
            None
        };
        
        // v2: Pass selected_breed to draw_ui
        self.display.draw_ui(
            &self.app.current_cells,
            status_text.as_deref(),
            &pattern_name,
            mode,
            self.app.selected_breed,
        )
    }

    /// Handles key events
    /// 
    /// v2: Up/Down keys now cycle through breeds, Left/Right calls defineCurrentPatternProgram
    fn handle_key_event(&mut self, key: crossterm::event::KeyEvent) -> Result<()> {
        match key.code {
            KeyCode::Char('q') => {
                self.app.running = false;
            }
            KeyCode::Char('s') => {
                // Toggle status visibility
                let program = switch_status_visibility_program(&self.app.current_core);
                execute_program(program, &mut self.app, &mut self.display, &self.store)?;
            }
            KeyCode::Char('p') => {
                // Toggle clock/step mode
                self.app.is_clock_mode = !self.app.is_clock_mode;
            }
            KeyCode::Char('h') => {
                // Show help
                self.app.status_text = Some(HELP_TEXT.to_string());
            }
            KeyCode::Enter => {
                if !self.app.is_clock_mode {
                    // Step in step mode
                    let program = next_generation_program(&self.app.current_core);
                    execute_program(program, &mut self.app, &mut self.display, &self.store)?;
                }
            }
            // v2: Up/Down keys for breed selection
            KeyCode::Up => {
                // Cycle selected_breed up (0-15)
                self.app.selected_breed = Breed::new(self.app.selected_breed.value().wrapping_add(1));
            }
            KeyCode::Down => {
                // Cycle selected_breed down (0-15)
                self.app.selected_breed = Breed::new(self.app.selected_breed.value().wrapping_sub(1));
            }
            // v2: Left/Right now update core's current_pattern via defineCurrentPatternProgram
            KeyCode::Left => {
                if !self.app.pattern_names.is_empty() {
                    if self.app.selected_pattern_idx == 0 {
                        self.app.selected_pattern_idx = self.app.pattern_names.len() - 1;
                    } else {
                        self.app.selected_pattern_idx -= 1;
                    }
                    // NEW v2: Update core's current_pattern
                    let pattern_name = &self.app.pattern_names[self.app.selected_pattern_idx];
                    let program = define_current_pattern_program(&self.app.current_core, pattern_name);
                    execute_program(program, &mut self.app, &mut self.display, &self.store)?;
                }
            }
            KeyCode::Right => {
                if !self.app.pattern_names.is_empty() {
                    self.app.selected_pattern_idx = (self.app.selected_pattern_idx + 1) % self.app.pattern_names.len();
                    // NEW v2: Update core's current_pattern
                    let pattern_name = &self.app.pattern_names[self.app.selected_pattern_idx];
                    let program = define_current_pattern_program(&self.app.current_core, pattern_name);
                    execute_program(program, &mut self.app, &mut self.display, &self.store)?;
                }
            }
            _ => {}
        }
        Ok(())
    }

    /// Handles mouse events
    /// 
    /// v2: Click now creates a Cell with selected breed as reference
    fn handle_mouse_event(&mut self, mouse: crossterm::event::MouseEvent) -> Result<()> {
        if let MouseEventKind::Down(MouseButton::Left) = mouse.kind {
            let (screen_col, screen_row) = (mouse.column, mouse.row);
            let (world_x, world_y) = screen_to_world(
                screen_col - 1,
                screen_row - 1,
                self.display.view_x,
                self.display.view_y,
            );

            // Get current display dimensions
            let (_width, height) = crossterm::terminal::size()?;
            let grid_height = height.saturating_sub(2);
            
            // Check if click is in the grid content area (not in borders or status bar)
            if screen_col < 1 || screen_row < 1 {
                return Ok(());  // In left or top border
            }
            
            if screen_row >= grid_height.saturating_sub(1) {
                return Ok(());  // In status bar or bottom border
            }

            if screen_col >= self.display.width.saturating_sub(1) {
                return Ok(());  // In right border
            }

            // v2: Create Cell with selected breed as reference
            let position = Position::new(world_x, world_y);
            let reference = Cell::new(position, self.app.selected_breed);
            
            // Use sentinel "current" — core resolves to current_pattern
            let program = add_pattern_program(&self.app.current_core, "current", &reference);
            execute_program(program, &mut self.app, &mut self.display, &self.store)?;
        }
        Ok(())
    }
}
