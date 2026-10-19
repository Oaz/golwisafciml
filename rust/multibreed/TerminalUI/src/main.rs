//! Terminal UI shell for Conway's Game of Life (v2) - Multi-Breed
//!
//! This is the imperative shell implementation using Ratatui for terminal rendering
//! and rusqlite for SQLite pattern persistence with breed support.

use anyhow::Result;
use app::Application;
use config::DATABASE_FILE;

mod app;
mod config;
mod demo;
mod events;
mod interpreter;
mod store;
mod ui;

fn main() -> Result<()> {
    // Initialize the application
    let mut application = Application::new(DATABASE_FILE)?;

    // Run the main event loop
    let result = application.run();

    // Cleanup and return
    result
}
