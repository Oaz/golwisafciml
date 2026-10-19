//! SQLite-based PatternStore port implementation with breed support

use crate::store::catalog::get_catalog;
use anyhow::{Context, Result};
use gol_multibreed_core::{Breed, Cell, Cells, Position};
use rusqlite::Connection;

/// SQLite implementation of the PatternStore port
/// 
/// v2: Schema now includes breed column for per-cell breed storage
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
    /// 
    /// v2: Added breed column to the schema
    pub fn initialize(&self) -> Result<()> {
        self.conn
            .execute(
                "CREATE TABLE IF NOT EXISTS patterns (
                    name TEXT NOT NULL,
                    x INTEGER NOT NULL,
                    y INTEGER NOT NULL,
                    breed INTEGER NOT NULL DEFAULT 0
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
    /// 
    /// v2: Patterns are stored with breed 0 (default)
    pub fn seed_catalog(&self) -> Result<()> {
        let catalog = get_catalog();

        // Clear existing patterns
        self.conn
            .execute("DELETE FROM patterns", [])
            .context("Failed to clear existing patterns")?;

        // Insert all patterns with breed 0
        for (name, cells) in &catalog {
            for cell in cells.values() {
                self.conn.execute(
                    "INSERT INTO patterns (name, x, y, breed) VALUES (?, ?, ?, ?)",
                    (name.as_str(), cell.position.x, cell.position.y, cell.breed.value()),
                )
                .with_context(|| format!("Failed to insert pattern cell for {}", name))?;
            }
        }

        Ok(())
    }

    /// Gets the cells for a pattern by name
    /// Returns empty Cells if the pattern is not found
    /// 
    /// v2: Returns Cells (HashMap<Position, Cell> with breed information)
    pub fn get(&self, name: &str) -> Result<Cells> {
        let mut stmt = self
            .conn
            .prepare("SELECT x, y, breed FROM patterns WHERE name = ? ORDER BY x, y")
            .context("Failed to prepare select statement")?;

        let mut cells = Cells::new();
        let rows = stmt
            .query_map([name], |row| {
                let x: i32 = row.get(0)?;
                let y: i32 = row.get(1)?;
                let breed_value: u8 = row.get(2)?;
                Ok(Cell::new(Position::new(x, y), Breed::new(breed_value)))
            })
            .context("Failed to query pattern cells")?;

        for row in rows {
            match row {
                Ok(cell) => { cells.insert(cell.position, cell); },
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
    
    /// NEW for v2: Save a pattern with its cells (including breeds)
    pub fn save(&self, name: &str, cells: &Cells) -> Result<()> {
        // Start a transaction - we need to use execute_batch or multiple execute calls
        // Since transaction() requires mutable access, we'll execute directly
        self.conn.execute("DELETE FROM patterns WHERE name = ?", [name])
            .context("Failed to delete existing pattern")?;
        
        // Insert all cells with their breeds
        for cell in cells.values() {
            self.conn.execute(
                "INSERT INTO patterns (name, x, y, breed) VALUES (?, ?, ?, ?)",
                (name, cell.position.x, cell.position.y, cell.breed.value()),
            )
            .with_context(|| format!("Failed to insert cell for pattern {}", name))?;
        }
        
        Ok(())
    }
    
    /// NEW for v2: Remove a pattern by name
    pub fn remove(&self, name: &str) -> Result<()> {
        self.conn.execute("DELETE FROM patterns WHERE name = ?", [name])?;
        Ok(())
    }
    
    /// NEW for v2: Clear all patterns from the store
    pub fn clear(&self) -> Result<()> {
        self.conn.execute("DELETE FROM patterns", [])?;
        Ok(())
    }
    
    /// NEW for v2: Returns a sorted list of all pattern names
    /// Alias for get_pattern_names for v2 authoring operations
    pub fn names(&self) -> Result<Vec<String>> {
        self.get_pattern_names()
    }
}
