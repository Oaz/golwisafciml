//! UI components for the TerminalUI
//!
//! Contains grid, status bar, and pattern selector widgets

use gol_classic_core::Cells;

/// Grid widget for rendering cells
#[allow(dead_code)]
pub struct Grid {
    cells: Cells,
    visible_width: u16,
    visible_height: u16,
    offset_x: i32,
    offset_y: i32,
}

#[allow(dead_code)]
impl Grid {
    /// Creates a new Grid widget
    pub fn new(cells: Cells) -> Self {
        Self {
            cells,
            visible_width: 0,
            visible_height: 0,
            offset_x: 0,
            offset_y: 0,
        }
    }

    /// Sets the visible area
    pub fn with_visible_area(mut self, width: u16, height: u16) -> Self {
        self.visible_width = width;
        self.visible_height = height;
        self
    }

    /// Sets the offset for panning
    pub fn with_offset(mut self, x: i32, y: i32) -> Self {
        self.offset_x = x;
        self.offset_y = y;
        self
    }

    /// Updates the cells
    pub fn with_cells(mut self, cells: Cells) -> Self {
        self.cells = cells;
        self
    }
}

/// Status bar widget
#[allow(dead_code)]
pub struct StatusBar {
    message: Option<String>,
    pattern_name: String,
    mode: String,
}

#[allow(dead_code)]
impl StatusBar {
    /// Creates a new StatusBar
    pub fn new(pattern_name: &str, mode: &str) -> Self {
        Self {
            message: None,
            pattern_name: pattern_name.to_string(),
            mode: mode.to_string(),
        }
    }

    /// Sets the status message
    pub fn with_message(mut self, message: &str) -> Self {
        self.message = Some(message.to_string());
        self
    }

    /// Hides the message
    pub fn hide_message(mut self) -> Self {
        self.message = None;
        self
    }

    /// Updates the pattern name
    pub fn with_pattern_name(mut self, name: &str) -> Self {
        self.pattern_name = name.to_string();
        self
    }

    /// Updates the mode
    pub fn with_mode(mut self, mode: &str) -> Self {
        self.mode = mode.to_string();
        self
    }
}

/// Pattern selector widget
#[allow(dead_code)]
pub struct PatternSelector {
    pattern_names: Vec<String>,
    selected_index: usize,
    visible: bool,
}

#[allow(dead_code)]
impl PatternSelector {
    /// Creates a new PatternSelector
    pub fn new(pattern_names: Vec<String>) -> Self {
        Self {
            pattern_names,
            selected_index: 0,
            visible: false,
        }
    }

    /// Selects the next pattern
    pub fn next(mut self) -> Self {
        if !self.pattern_names.is_empty() {
            self.selected_index = (self.selected_index + 1) % self.pattern_names.len();
        }
        self
    }

    /// Selects the previous pattern
    pub fn previous(mut self) -> Self {
        if !self.pattern_names.is_empty() {
            if self.selected_index == 0 {
                self.selected_index = self.pattern_names.len() - 1;
            } else {
                self.selected_index = self.selected_index - 1;
            }
        }
        self
    }

    /// Sets visibility
    pub fn with_visibility(mut self, visible: bool) -> Self {
        self.visible = visible;
        self
    }

    /// Gets the currently selected pattern name
    pub fn selected_pattern(&self) -> Option<&str> {
        self.pattern_names.get(self.selected_index).map(|s| s.as_str())
    }

    /// Gets the selected index
    pub fn selected_index(&self) -> usize {
        self.selected_index
    }
}
