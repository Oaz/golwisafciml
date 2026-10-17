//! Shell configuration constants

/// Character for live cell (U+2588 FULL BLOCK)
pub const LIVE_CELL: &str = "█";

/// Character for dead cell (space)
pub const DEAD_CELL: &str = " ";

/// Database file name
pub const DATABASE_FILE: &str = "patterns.db";

/// Help text for status bar
pub const HELP_TEXT: &str = "q:quit | s:status | p:clock/step | ←→:select pattern | Enter:step | Click:stamp";
