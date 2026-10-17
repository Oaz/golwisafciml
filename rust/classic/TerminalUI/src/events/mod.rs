//! EventSource module

pub use crossterm::EventSource;

mod crossterm;

use ::crossterm::event::{KeyEvent, MouseEvent};

/// Event types for the shell
#[derive(Debug, Clone)]
pub enum Event {
    /// Tick event (clock-driven)
    Tick,
    /// Key press event
    Key(KeyEvent),
    /// Mouse event
    Mouse(MouseEvent),
    /// Terminal resize event
    Resize(u16, u16),
    /// Quit event
    #[allow(dead_code)]
    Quit,
}
