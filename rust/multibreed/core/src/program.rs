use std::fmt;

use crate::cell::Cell;
use crate::cells::Cells;
use crate::core::Core;
use crate::life::next_generation_cells;
use crate::pattern::{status_message, translate};

/// The Program type encodes the instruction protocol between core and shell.
/// This is a flat enum that directly encodes the spec's instruction set.
///
/// Per spec §3.2: Program ::= Finish | Step Instruction Rest
/// but we use a flat enum which is equivalent (spec §10).
pub enum Program {
    /// Terminate and commit this Core state (spec's Finish)
    Return(Core),

    /// Show cells on display, then continue
    ShowCells(Cells, Box<Program>),

    /// Display a message, then continue
    DisplayMessage(String, Box<Program>),

    /// Hide the message, then continue
    HideMessage(Box<Program>),

    /// Get pattern cells by name; continuation receives Cells response
    GetPatternCells(String, Box<dyn FnOnce(Cells) -> Program + Send + Sync>),
}

impl Program {
    /// Creates a Return program.
    pub fn ret(core: Core) -> Self {
        Program::Return(core)
    }

    /// Creates a ShowCells program.
    pub fn show_cells(cells: Cells, next: Program) -> Self {
        Program::ShowCells(cells, Box::new(next))
    }

    /// Creates a DisplayMessage program.
    pub fn display_message(msg: String, next: Program) -> Self {
        Program::DisplayMessage(msg, Box::new(next))
    }

    /// Creates a HideMessage program.
    pub fn hide_message(next: Program) -> Self {
        Program::HideMessage(Box::new(next))
    }

    /// Creates a GetPatternCells program.
    pub fn get_pattern_cells(
        name: String,
        cont: impl FnOnce(Cells) -> Program + Send + Sync + 'static,
    ) -> Self {
        Program::GetPatternCells(name, Box::new(cont))
    }
}

/// Manual Debug implementation because GetPatternCells contains a trait object
/// that doesn't implement Debug.
impl fmt::Debug for Program {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Program::Return(core) => f.debug_tuple("Return").field(core).finish(),
            Program::ShowCells(cells, _) => f.debug_tuple("ShowCells").field(cells).finish(),
            Program::DisplayMessage(msg, _) => f.debug_tuple("DisplayMessage").field(msg).finish(),
            Program::HideMessage(_) => write!(f, "HideMessage"),
            Program::GetPatternCells(name, _) => {
                f.debug_tuple("GetPatternCells").field(name).finish()
            }
        }
    }
}

/// Manual PartialEq implementation because GetPatternCells contains a trait object
/// that doesn't implement PartialEq. For GetPatternCells, we only compare the name.
impl PartialEq for Program {
    fn eq(&self, other: &Self) -> bool {
        match (self, other) {
            (Program::Return(a), Program::Return(b)) => a == b,
            (Program::ShowCells(a_cells, a_next), Program::ShowCells(b_cells, b_next)) => {
                a_cells == b_cells && a_next == b_next
            }
            (Program::DisplayMessage(a_msg, a_next), Program::DisplayMessage(b_msg, b_next)) => {
                a_msg == b_msg && a_next == b_next
            }
            (Program::HideMessage(a_next), Program::HideMessage(b_next)) => a_next == b_next,
            // GetPatternCells cannot be fully compared due to trait object
            // We only compare the pattern name
            (Program::GetPatternCells(a_name, _), Program::GetPatternCells(b_name, _)) => {
                a_name == b_name
            }
            _ => false,
        }
    }
}

/// Creates a program that computes the next generation.
/// If status is visible, it displays the status message before showing cells.
/// Otherwise, it just shows the new cells.
pub fn next_generation_program(core: &Core) -> Program {
    let new_cells = next_generation_cells(&core.cells);
    let core_with_new = core.clone().with_cells(new_cells.clone());

    if core.status_visible {
        Program::display_message(
            status_message(&new_cells),
            Program::show_cells(new_cells, Program::ret(core_with_new)),
        )
    } else {
        Program::show_cells(new_cells, Program::ret(core_with_new))
    }
}

/// Creates a program that adds a pattern to the current cells.
/// The pattern is fetched by name, translated by the reference cell,
/// and merged with existing cells.
///
/// v2 changes:
/// - reference is now a Cell (carrying position and breed)
/// - pattern_name can be the sentinel "current" which resolves to core.current_pattern
///
/// Core operations are in the same crate, so can access private fields directly.
pub fn add_pattern_program(core: &Core, pattern_name: &str, reference: &Cell) -> Program {
    let core_ref = core.clone();
    let reference_clone = *reference;

    // Resolve "current" sentinel to the stored current_pattern name
    // Core operations are in the same crate, so can access private fields directly
    let resolved_name = if pattern_name == "current" {
        core_ref.current_pattern.clone()
    } else {
        pattern_name.to_string()
    };

    Program::get_pattern_cells(resolved_name, move |pattern_cells: Cells| {
        let translated = translate(&pattern_cells, &reference_clone);

        // Merge: union of existing cells and translated pattern cells
        // If a position exists in both, the translated cell overwrites
        let new_cells = core_ref.cells.clone().union(translated);
        let core_with_new = core_ref.clone().with_cells(new_cells.clone());

        if core_ref.status_visible {
            Program::display_message(
                status_message(&core_with_new.cells),
                Program::show_cells(core_with_new.cells.clone(), Program::ret(core_with_new)),
            )
        } else {
            Program::show_cells(core_with_new.cells.clone(), Program::ret(core_with_new))
        }
    })
}

/// Creates a program that sets the current pattern name in core state.
/// This is the v2 evolution: pattern selection is promoted from shell config to core state.
///
/// Emits only Return instruction (no effectful instructions).
pub fn define_current_pattern_program(core: &Core, name: &str) -> Program {
    let new_core = core.clone().with_current_pattern(name.to_string());
    Program::ret(new_core)
}

/// Creates a program that toggles status visibility.
/// If turning on, displays the current status message.
/// If turning off, hides the message.
///
/// This is unchanged from v1.
pub fn switch_status_visibility_program(core: &Core) -> Program {
    if core.status_visible {
        Program::hide_message(Program::ret(core.clone().with_status_visible(false)))
    } else {
        Program::display_message(
            status_message(&core.cells),
            Program::ret(core.clone().with_status_visible(true)),
        )
    }
}
