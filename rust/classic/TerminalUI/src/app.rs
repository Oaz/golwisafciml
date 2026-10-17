//! Main application logic and event loop

use anyhow::Result;
use crossterm::event::{KeyCode, MouseButton, MouseEventKind};
use gol_classic_core::{add_pattern_program, next_generation_program, switch_status_visibility_program, Core, Position};

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

        // Seed the catalog
        store.seed_catalog()?;

        // Get pattern names
        let pattern_names = store.get_pattern_names()?;

        // Initialize app state
        let mut app = App::new(pattern_names);

        // Initialize core with status hidden (v1 spec)
        app.current_core = app.current_core.with_status_visible(false);
        app.status_visible = false;

        // Seed demo scene and get the cells
        let initial_core = Core::initial().with_status_visible(false);
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
        let pattern_name = self.app.pattern_names[self.app.selected_pattern_idx].clone();
        let mode = if self.app.is_clock_mode { "clock" } else { "step" };
        
        // Show cell count in status bar only if status is visible
        let status_text = if self.app.status_visible {
            Some(gol_classic_core::status_message(&self.app.current_cells))
        } else {
            None
        };
        
        self.display.draw_ui(
            &self.app.current_cells,
            status_text.as_deref(),
            &pattern_name,
            mode,
        )
    }

    /// Handles key events
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
            KeyCode::Left => {
                // Previous pattern
                if !self.app.pattern_names.is_empty() {
                    if self.app.selected_pattern_idx == 0 {
                        self.app.selected_pattern_idx = self.app.pattern_names.len() - 1;
                    } else {
                        self.app.selected_pattern_idx -= 1;
                    }
                }
            }
            KeyCode::Right => {
                // Next pattern
                if !self.app.pattern_names.is_empty() {
                    self.app.selected_pattern_idx =
                        (self.app.selected_pattern_idx + 1) % self.app.pattern_names.len();
                }
            }
            _ => {}
        }
        Ok(())
    }

    /// Handles mouse events
    fn handle_mouse_event(&mut self, mouse: crossterm::event::MouseEvent) -> Result<()> {
        if let MouseEventKind::Down(MouseButton::Left) = mouse.kind {
            // Get screen coordinates (crossterm: column is x, row is y)
            let screen_col = mouse.column;
            let screen_row = mouse.row;

            // Get current display dimensions
            let (_width, height) = crossterm::terminal::size()?;
            
            // The grid area is height - 2 (for status bar which takes 2 lines)
            let grid_height = height.saturating_sub(2);
            
            // The grid area takes height - 2 lines (status bar takes 2 lines)
            // Grid has Borders::ALL, so content is inset by 1 on all sides
            // Check if click is in the grid content area
            if screen_row >= grid_height {
                return Ok(());  // In status bar
            }
            
            // Account for grid borders: content is from (1, 1) to (width-2, grid_height-2)
            if screen_col < 1 || screen_row < 1 {
                return Ok(());  // In left or top border
            }
            
            // Check right and bottom borders
            if screen_col >= self.display.width.saturating_sub(1) || screen_row >= grid_height.saturating_sub(1) {
                return Ok(());  // In right or bottom border
            }

            // Map screen coordinates to world coordinates
            // Subtract 1 for the left border and 1 for the top border
            let (world_x, world_y) = screen_to_world(
                screen_col - 1,
                screen_row - 1,
                self.display.view_x,
                self.display.view_y,
            );

            // Get selected pattern name
            let pattern_name = self.app.pattern_names[self.app.selected_pattern_idx].clone();

            // Add pattern at click position
            let reference = Position::new(world_x, world_y);
            let program = add_pattern_program(&self.app.current_core, &pattern_name, &reference);
            execute_program(program, &mut self.app, &mut self.display, &self.store)?;
        }
        Ok(())
    }
}
