//! Program interpreter for the instruction protocol

use anyhow::Result;
use gol_classic_core::{Cells, Core, Program};

use crate::store::PatternStore;
use crate::ui::Display;

/// The shell application state
pub struct App {
    /// Current core state
    pub current_core: Core,
    /// List of available pattern names
    pub pattern_names: Vec<String>,
    /// Index of the currently selected pattern
    pub selected_pattern_idx: usize,
    /// Whether in clock mode (true) or step mode (false)
    pub is_clock_mode: bool,
    /// Whether the application is running
    pub running: bool,
    /// Cached status text for UI rendering
    pub status_text: Option<String>,
    /// Cached cells from the last ShowCells instruction
    pub current_cells: gol_classic_core::Cells,
    /// Cached status visibility from the last program execution
    pub status_visible: bool,
}

impl App {
    /// Creates a new App with initial state
    pub fn new(pattern_names: Vec<String>) -> Self {
        Self {
            current_core: Core::initial(),
            pattern_names,
            selected_pattern_idx: 0,
            is_clock_mode: false,
            running: true,
            status_text: None,
            current_cells: gol_classic_core::Cells::new(),
            status_visible: false, // v1 spec: status hidden at startup
        }
    }
}

/// Interprets a program by executing its instructions
/// 
/// This is the main interpreter that processes the Program enum returned by core operations.
/// It dispatches on each instruction and executes the corresponding effect through the ports.
pub fn interpret(
    program: Program,
    app: &mut App,
    display: &mut Display,
    store: &PatternStore,
) -> Result<()> {
    // We'll use an iterative approach to avoid stack overflow
    // The program is processed by repeatedly matching and executing the outer layer
    
    let mut current_program = program;
    
    loop {
        match current_program {
            Program::Return(core) => {
                app.current_core = core;
                return Ok(());
            }
            Program::ShowCells(cells, next) => {
                display.render(&cells)?;
                app.status_text = None; // Clear status when showing cells
                // Cache the current cells
                app.current_cells = cells.clone();
                current_program = *next;
            }
            Program::DisplayMessage(msg, next) => {
                display.show(&msg)?;
                app.status_text = Some(msg.clone());
                // If we see a DisplayMessage with a status message, status is visible
                // Status messages start with a number or "No cell"
                if !msg.is_empty() && (msg.chars().next().unwrap().is_ascii_digit() || msg.starts_with("No cell")) {
                    app.status_visible = true;
                }
                current_program = *next;
            }
            Program::HideMessage(next) => {
                display.hide()?;
                app.status_text = None;
                // When message is hidden, status is not visible
                app.status_visible = false;
                current_program = *next;
            }
            Program::GetPatternCells(name, cont) => {
                // Get the pattern cells from the store
                let pattern_cells: Cells = match store.get(&name) {
                    Ok(cells) => cells,
                    Err(_) => Cells::new(),
                };
                
                // Call the continuation with the cells
                current_program = cont(pattern_cells);
            }
        }
    }
}

/// Executes a single program to completion
/// This is a convenience wrapper around interpret
pub fn execute_program(
    program: Program,
    app: &mut App,
    display: &mut Display,
    store: &PatternStore,
) -> Result<()> {
    interpret(program, app, display, store)
}
