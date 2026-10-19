use crate::cells::Cells;

/// The core state of the Game of Life simulation.
/// This is an opaque type - its fields are private to external crates but
/// accessible within the same crate (for program construction and testing).
/// All data flows out through Program instructions.
///
/// For v2 (multi-breed), this includes the current_pattern field.
#[derive(Debug, Clone, PartialEq)]
pub struct Core {
    pub(crate) cells: Cells,
    pub(crate) status_visible: bool,
    pub(crate) current_pattern: String,
}

impl Core {
    /// Creates the initial core state with empty cells, status not visible, and no current pattern.
    pub fn initial() -> Self {
        Self {
            cells: Cells::new(),
            status_visible: false,
            current_pattern: String::new(),
        }
    }

    /// Returns a new Core with the given cells, keeping other fields unchanged.
    pub fn with_cells(self, cells: Cells) -> Self {
        Self { cells, ..self }
    }

    /// Returns a new Core with the given status visibility, keeping other fields unchanged.
    pub fn with_status_visible(self, status_visible: bool) -> Self {
        Self {
            status_visible,
            ..self
        }
    }

    /// Returns a new Core with the given current pattern name, keeping other fields unchanged.
    pub fn with_current_pattern(self, current_pattern: String) -> Self {
        Self {
            current_pattern,
            ..self
        }
    }
}
