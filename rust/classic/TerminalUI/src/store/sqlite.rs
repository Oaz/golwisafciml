//! SQLite-based PatternStore port implementation

use crate::store::catalog::get_catalog;
use anyhow::{Context, Result};
use gol_classic_core::{Cells, Position};
use rusqlite::Connection;

/// SQLite implementation of the PatternStore port
pub struct PatternStore {
    conn: Connection,
}

impl PatternStore {
    /// Creates a new PatternStore and initializes the database schema
    pub fn new(path: &str) -> Result<Self> {
        let conn = Connection::open(path)
            .with_context(|| format!("Failed to open database at {}", path))?;

        // Enable WAL mode for better concurrent access
        conn.pragma_update(None, "journal_mode", "WAL")
            .context("Failed to set journal mode")?;

        // Initialize schema
        let store = Self { conn };
        store.initialize()?;

        Ok(store)
    }

    /// Initializes the database schema if it doesn't exist
    pub fn initialize(&self) -> Result<()> {
        self.conn
            .execute(
                "CREATE TABLE IF NOT EXISTS patterns (
                    name TEXT NOT NULL,
                    x INTEGER NOT NULL,
                    y INTEGER NOT NULL
                )",
                [],
            )
            .context("Failed to create patterns table")?;

        self.conn
            .execute(
                "CREATE INDEX IF NOT EXISTS idx_patterns_name ON patterns(name)",
                [],
            )
            .context("Failed to create index")?;

        Ok(())
    }

    /// Seeds the catalog with all 19 patterns from Appendix A
    pub fn seed_catalog(&self) -> Result<()> {
        let catalog = get_catalog();

        // Clear existing patterns
        self.conn
            .execute("DELETE FROM patterns", [])
            .context("Failed to clear existing patterns")?;

        // Insert all patterns
        for (name, cells) in &catalog {
            for &Position { x, y } in cells {
                self.conn.execute(
                    "INSERT INTO patterns (name, x, y) VALUES (?, ?, ?)",
                    (name.as_str(), x, y),
                )
                .with_context(|| format!("Failed to insert pattern cell for {}", name))?;
            }
        }

        Ok(())
    }

    /// Gets the cells for a pattern by name
    /// Returns empty Cells if the pattern is not found
    pub fn get(&self, name: &str) -> Result<Cells> {
        let mut stmt = self
            .conn
            .prepare("SELECT x, y FROM patterns WHERE name = ? ORDER BY x, y")
            .context("Failed to prepare select statement")?;

        let mut cells = Cells::new();
        let rows = stmt
            .query_map([name], |row| {
                Ok(Position::new(row.get::<_, i32>(0)?, row.get::<_, i32>(1)?))
            })
            .context("Failed to query pattern cells")?;

        for row in rows {
            match row {
                Ok(pos) => { cells.insert(pos); },
                Err(_) => continue,
            }
        }

        Ok(cells)
    }

    /// Returns a sorted list of all pattern names in the store
    pub fn get_pattern_names(&self) -> Result<Vec<String>> {
        let mut stmt = self
            .conn
            .prepare("SELECT DISTINCT name FROM patterns ORDER BY name")
            .context("Failed to prepare select names statement")?;

        let mut names = Vec::new();
        let rows = stmt
            .query_map([], |row| row.get::<_, String>(0))
            .context("Failed to query pattern names")?;

        for row in rows {
            match row {
                Ok(name) => { names.push(name); },
                Err(_) => continue,
            }
        }

        Ok(names)
    }
}


