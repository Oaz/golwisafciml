//! Shell configuration constants for v2 Multi-Breed

/// Character for live cell (U+2588 FULL BLOCK)
pub const LIVE_CELL: &str = "█";

/// Character for dead cell (space)
pub const DEAD_CELL: &str = " ";

/// Database file name
pub const DATABASE_FILE: &str = "patterns.db";

/// Clock interval for v2 (200ms = 0.2s) - from Appendix F
pub const CLOCK_INTERVAL_MS: u64 = 200;

/// Help text for status bar - v2 with breed selection
pub const HELP_TEXT: &str = "q:quit | s:status | p:clock/step | ↑↓:breed | ←→:select pattern | Enter:step | Click:stamp";
