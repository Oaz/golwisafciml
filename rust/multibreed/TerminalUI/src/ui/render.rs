//! Ratatui-based Display port implementation with breed colors

use anyhow::{Context, Result};
use gol_multibreed_core::{Cell, Cells, Position};
use ratatui::backend::CrosstermBackend;
use ratatui::layout::{Constraint, Direction, Layout, Rect};
use ratatui::prelude::Stylize;
use ratatui::style::{Color, Style};
use ratatui::text::{Line, Span, Text};
use ratatui::widgets::{Block, Borders, Paragraph};
use ratatui::{Frame, Terminal};
use std::io::stdout;

use crate::config::{DEAD_CELL, LIVE_CELL};
use crate::ui::palette::breed_to_color;

/// Display port implementation using Ratatui
pub struct Display {
    terminal: Terminal<CrosstermBackend<std::io::Stdout>>,
    /// Terminal dimensions
    pub width: u16,
    pub height: u16,
    /// Current viewport offset (world coordinates of top-left corner of viewport)
    pub view_x: i32,
    pub view_y: i32,
}

impl Display {
    /// Creates a new Display
    pub fn new() -> Result<Self> {
        let backend = CrosstermBackend::new(stdout());
        let terminal = Terminal::new(backend)
            .context("Failed to create terminal")?;

        Ok(Self {
            terminal,
            width: 0,
            height: 0,
            view_x: 0,
            view_y: 0,
        })
    }

    /// Renders the given cells with breed colors
    pub fn render(&mut self, cells: &Cells) -> Result<()> {
        self.terminal
            .draw(|f| {
                let size = f.area();
                draw_frame(f, cells, None, "", "", size.width, size.height, self.view_x, self.view_y, gol_multibreed_core::Breed::new(0));
            })
            .context("Failed to draw frame")?;
        Ok(())
    }

    /// Shows a status message
    pub fn show(&mut self, text: &str) -> Result<()> {
        let empty_cells = Cells::new();
        self.terminal
            .draw(|f| {
                let size = f.area();
                draw_frame(f, &empty_cells, Some(text), "", "", size.width, size.height, self.view_x, self.view_y, gol_multibreed_core::Breed::new(0));
            })
            .context("Failed to draw frame with message")?;
        Ok(())
    }

    /// Hides the status message
    pub fn hide(&mut self) -> Result<()> {
        let empty_cells = Cells::new();
        self.terminal
            .draw(|f| {
                let size = f.area();
                draw_frame(f, &empty_cells, None, "", "", size.width, size.height, self.view_x, self.view_y, gol_multibreed_core::Breed::new(0));
            })
            .context("Failed to draw frame without message")?;
        Ok(())
    }

    /// Draws the full UI with cells, status, pattern name, mode, and breed selector
    pub fn draw_ui(
        &mut self,
        cells: &Cells,
        status: Option<&str>,
        pattern_name: &str,
        mode: &str,
        selected_breed: gol_multibreed_core::Breed,
    ) -> Result<()> {
        self.terminal
            .draw(|f| {
                let size = f.area();
                draw_frame(f, cells, status, pattern_name, mode, size.width, size.height, self.view_x, self.view_y, selected_breed);
            })
            .context("Failed to draw UI")?;
        
        Ok(())
    }

    /// Handles terminal resize and centers the viewport on the demo scene
    pub fn handle_resize(&mut self, width: u16, height: u16) -> Result<()> {
        self.width = width;
        self.height = height;
        
        // Start with viewport at 0,0 - we'll center it based on cells
        self.view_x = 0;
        self.view_y = 0;
        
        Ok(())
    }

    /// Centers the viewport on the given cells
    #[allow(dead_code)]
    pub fn center_on_cells(&mut self, cells: &Cells) {
        if cells.is_empty() {
            return;
        }
        
        // Find the bounds of the cells
        let mut min_x = i32::MAX;
        let mut max_x = i32::MIN;
        let mut min_y = i32::MAX;
        let mut max_y = i32::MIN;
        
        for cell in cells.values() {
            min_x = min_x.min(cell.position.x);
            max_x = max_x.max(cell.position.x);
            min_y = min_y.min(cell.position.y);
            max_y = max_y.max(cell.position.y);
        }
        
        // Calculate center
        let center_x = (min_x + max_x) / 2;
        let center_y = (min_y + max_y) / 2;
        
        // Center the viewport on the cells
        let content_width = self.width.saturating_sub(2);
        let content_height = self.height.saturating_sub(4);
        
        self.view_x = center_x - (content_width as i32 / 2);
        self.view_y = center_y - (content_height as i32 / 2);
    }

    /// Centers the viewport on the demo scene (known coordinates from Appendix C)
    pub fn center_on_demo_scene(&mut self) {
        // Demo scene from Appendix C has stamps at:
        // x: 12-40, y: 10-35
        // Center is approximately at (25, 22)
        let center_x = 25;
        let center_y = 22;
        
        // Center the viewport on this position
        let content_width = self.width.saturating_sub(2);
        let content_height = self.height.saturating_sub(4);
        
        self.view_x = center_x - (content_width as i32 / 2);
        self.view_y = center_y - (content_height as i32 / 2);
    }
}

/// Static function to draw a frame with colored cells
fn draw_frame(
    f: &mut Frame<'_>,
    cells: &Cells,
    status: Option<&str>,
    pattern_name: &str,
    mode: &str,
    _width: u16,
    _height: u16,
    view_x: i32,
    view_y: i32,
    selected_breed: gol_multibreed_core::Breed,
) {
    let size = f.area();
    
    // Split into grid area and status bar
    let main_layout = Layout::default()
        .direction(Direction::Vertical)
        .constraints([
            Constraint::Min(size.height.saturating_sub(2)), // Grid: all but 2 lines for status bar
            Constraint::Length(2),                            // Status bar: 2 lines (border + content)
        ])
        .split(size);

    let grid_area = main_layout[0];
    let status_area = main_layout[1];

    // Draw grid with border and colored cells (white background for v2)
    let grid_buffer = create_colored_grid_buffer(cells, grid_area, view_x, view_y);
    let grid_block = Block::default()
        .title(" Conway's Game of Life (v2) - Multi-Breed ")
        .title_style(Style::default().fg(Color::Black))
        .borders(Borders::ALL)
        .border_style(Style::default().fg(Color::Black))
        .bg(Color::White);
    
    f.render_widget(Paragraph::new(grid_buffer).block(grid_block), grid_area);

    // Draw status bar with border at top and white background (v2)
    let breed_color = breed_to_color(selected_breed);
    
    // Create a block for the entire status bar
    let status_block = Block::default()
        .borders(Borders::TOP)
        .border_style(Style::default().fg(Color::Black))
        .bg(Color::White);
    
    // Create inner layout for left and right content
    let inner_layout = Layout::default()
        .direction(Direction::Horizontal)
        .constraints([
            Constraint::Min(0),     // Left: status message
            Constraint::Length(1),   // Spacer
            Constraint::Min(10),    // Right: controls
        ])
        .split(status_area);
    
    // Render the status block first
    f.render_widget(status_block, status_area);
    
    // Left: status message (if visible)
    if let Some(msg) = status {
        let status_paragraph = Paragraph::new(Text::from(Line::from(vec![
            Span::styled(format!("{} ", msg), Style::default().fg(Color::Black))
        ])));
        f.render_widget(status_paragraph, inner_layout[0]);
    }
    
    // Right: Pattern name, breed block, mode, and controls (right-aligned)
    let controls_text = Text::from(Line::from(vec![
        Span::styled(format!("Pattern: {}", pattern_name), Style::default().fg(Color::Black)),
        Span::styled(" | ", Style::default().fg(Color::Black)),
        Span::styled(" █ ", Style::default().fg(breed_color)),
        Span::styled(format!(" | Mode: {} | ↑↓:breed | ←→:select | Click:stamp", mode), Style::default().fg(Color::Black)),
    ]));
    let controls_paragraph = Paragraph::new(controls_text)
        .alignment(ratatui::layout::Alignment::Right);
    f.render_widget(controls_paragraph, inner_layout[2]);
}

/// Creates a colored grid buffer from cells
/// Each cell is rendered with its breed's color
fn create_colored_grid_buffer(
    cells: &Cells,
    area: Rect,
    view_x: i32,
    view_y: i32,
) -> Text<'static> {
    let mut lines = Vec::new();
    
    // Account for grid block borders: Borders::ALL uses top and bottom borders
    // So the actual content height is area.height - 2
    let content_height = area.height.saturating_sub(2);
    let content_width = area.width.saturating_sub(2);
    
    // Each row in the grid content area corresponds to a world y coordinate
    for row in 0..content_height {
        let world_y = view_y + (row as i32);
        let mut spans = Vec::new();
        
        for col in 0..content_width {
            let world_x = view_x + (col as i32);
            
            let pos = Position::new(world_x, world_y);
            
            // Find cell at this position
            if let Some(cell) = cells.get(&pos) {
                let color = breed_to_color(cell.breed);
                spans.push(Span::styled(
                    LIVE_CELL,
                    Style::default().fg(color),
                ));
            } else {
                spans.push(Span::raw(DEAD_CELL));
            }
        }
        lines.push(Line::from(spans));
    }
    
    Text::from(lines)
}

/// Maps screen coordinates (relative to grid content area, excluding borders) to world coordinates
/// screen_col: x coordinate within grid content area (left to right, 0-based, excluding left border)
/// screen_row: y coordinate within grid content area (top to bottom, 0-based, excluding top border)
/// view_x: viewport x offset
/// view_y: viewport y offset
/// Returns world (x, y) coordinates
pub fn screen_to_world(screen_col: u16, screen_row: u16, view_x: i32, view_y: i32) -> (i32, i32) {
    // Screen coordinates map directly to world coordinates with viewport offset
    let world_x = view_x + screen_col as i32;
    let world_y = view_y + screen_row as i32;
    (world_x, world_y)
}

