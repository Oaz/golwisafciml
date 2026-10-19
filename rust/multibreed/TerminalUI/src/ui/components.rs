//! UI components for the TerminalUI v2
//!
//! Contains grid, status bar, pattern selector, and breed selector widgets

use gol_multibreed_core::{Breed, Cells};

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

/// Breed selector widget for v2
pub struct BreedSelector {
    selected_breed: Breed,
}

impl BreedSelector {
    /// Creates a new BreedSelector with initial breed 0
    pub fn new() -> Self {
        Self {
            selected_breed: Breed::new(0),
        }
    }

    /// Creates a new BreedSelector with a specific breed
    pub fn with_breed(breed: Breed) -> Self {
        Self { selected_breed: breed }
    }

    /// Selects the next breed (0-15, wraps around)
    pub fn next(mut self) -> Self {
        self.selected_breed = Breed::new(self.selected_breed.value().wrapping_add(1));
        self
    }

    /// Selects the previous breed (0-15, wraps around)
    pub fn previous(mut self) -> Self {
        self.selected_breed = Breed::new(self.selected_breed.value().wrapping_sub(1));
        self
    }

    /// Gets the currently selected breed
    pub fn selected_breed(&self) -> Breed {
        self.selected_breed
    }

    /// Sets the selected breed
    pub fn set_breed(mut self, breed: Breed) -> Self {
        self.selected_breed = breed;
        self
    }
}
