//! Ratatui-based Display port implementation

use anyhow::{Context, Result};
use gol_classic_core::{Cells, Position};
use ratatui::backend::CrosstermBackend;
use ratatui::layout::{Constraint, Direction, Layout, Rect};
use ratatui::style::{Color, Style};
use ratatui::text::{Line, Span, Text};
use ratatui::widgets::{Block, Borders, Paragraph};
use ratatui::{Frame, Terminal};
use std::io::stdout;

use crate::config::{DEAD_CELL, LIVE_CELL};

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

    /// Renders the given cells
    pub fn render(&mut self, cells: &Cells) -> Result<()> {
        self.terminal
            .draw(|f| {
                let size = f.area();
                draw_frame(f, cells, None, "", "", size.width, size.height, self.view_x, self.view_y)
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
                draw_frame(f, &empty_cells, Some(text), "", "", size.width, size.height, self.view_x, self.view_y)
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
                draw_frame(f, &empty_cells, None, "", "", size.width, size.height, self.view_x, self.view_y)
            })
            .context("Failed to draw frame without message")?;
        Ok(())
    }

    /// Draws the full UI with cells, status, pattern name, and mode
    pub fn draw_ui(
        &mut self,
        cells: &Cells,
        status: Option<&str>,
        pattern_name: &str,
        mode: &str,
    ) -> Result<()> {
        self.terminal
            .draw(|f| {
                let size = f.area();
                draw_frame(f, cells, status, pattern_name, mode, size.width, size.height, self.view_x, self.view_y)
            })
            .context("Failed to draw UI frame")?;
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
        
        for pos in cells {
            min_x = min_x.min(pos.x);
            max_x = max_x.max(pos.x);
            min_y = min_y.min(pos.y);
            max_y = max_y.max(pos.y);
        }
        
        // Calculate center
        let center_x = (min_x + max_x) / 2;
        let center_y = (min_y + max_y) / 2;
        
        // Center the viewport on the cells
        // Grid area is height - 2 (for status bar which takes 2 lines)
        // Grid has Borders::ALL, so content area is (width - 2) x ((height - 2) - 2)
        // which simplifies to (width - 2) x (height - 4)
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
        // Grid area is height - 2 (for status bar which takes 2 lines)
        // Grid has Borders::ALL, so content area is (width - 2) x ((height - 2) - 2)
        // which simplifies to (width - 2) x (height - 4)
        let content_width = self.width.saturating_sub(2);
        let content_height = self.height.saturating_sub(4);
        
        self.view_x = center_x - (content_width as i32 / 2);
        self.view_y = center_y - (content_height as i32 / 2);
    }
}

/// Static function to draw a frame
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
) {
    let size = f.area();
    
    // Split into grid area and status bar
    // Grid block has Borders::ALL which uses 2 lines (top + bottom)
    // Status bar has Borders::TOP which uses 1 line for border
    // So we need: grid_content_height + 2 (grid borders) + 1 (status border) + 1 (status content) = total height
    // For simplicity, give status bar exactly 2 lines (1 for border, 1 for content)
    // and grid takes the rest minus 1 more for spacing
    let main_layout = Layout::default()
        .direction(Direction::Vertical)
        .constraints([
            Constraint::Min(size.height.saturating_sub(2)), // Grid: all but 2 lines for status bar
            Constraint::Length(2),                            // Status bar: 2 lines (border + content)
        ])
        .split(size);

    let grid_area = main_layout[0];
    let status_area = main_layout[1];

    // Draw grid with border
    let grid_buffer = create_grid_buffer(cells, grid_area, view_x, view_y);
    let grid_block = Block::default()
        .title(" Conway's Game of Life (v1) - Monochrome ")
        .borders(Borders::ALL)
        .border_style(Style::default().fg(Color::White));
    
    f.render_widget(Paragraph::new(grid_buffer).block(grid_block), grid_area);

    // Draw status bar with border at top
    let status_text = create_status_text(status, pattern_name, mode);
    let status_bar = Paragraph::new(status_text)
        .style(Style::default().fg(Color::White).bg(Color::DarkGray))
        .block(
            Block::default()
                .borders(Borders::TOP)
                .border_style(Style::default().fg(Color::White)),
        );
    f.render_widget(status_bar, status_area);
}

/// Creates a grid buffer from cells
/// Each character in the grid corresponds to a cell in world coordinates
/// starting from (view_x, view_y) at the top-left of the grid area
fn create_grid_buffer(
    cells: &Cells,
    area: Rect,
    view_x: i32,
    view_y: i32,
) -> String {
    let mut buffer = String::new();
    
    // Account for grid block borders: Borders::ALL uses top and bottom borders
    // So the actual content height is area.height - 2
    let content_height = area.height.saturating_sub(2);
    let content_width = area.width.saturating_sub(2);
    
    // Each row in the grid content area corresponds to a world y coordinate
    for row in 0..content_height {
        let world_y = view_y + (row as i32);
        
        for col in 0..content_width {
            let world_x = view_x + (col as i32);
            
            let pos = Position::new(world_x, world_y);
            if cells.contains(&pos) {
                buffer.push_str(LIVE_CELL);
            } else {
                buffer.push_str(DEAD_CELL);
            }
        }
        // Don't add newline after last row to prevent extra space
        if row < content_height.saturating_sub(1) {
            buffer.push('\n');
        }
    }
    
    buffer
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

/// Creates status bar text
fn create_status_text(status: Option<&str>, pattern_name: &str, mode: &str) -> Text<'static> {
    let mut spans = Vec::new();

    // Left side: status message or empty if not visible
    if let Some(msg) = status {
        spans.push(Span::styled(
            format!(" {} ", msg),
            Style::default().fg(Color::White),
        ));
    } else {
        // When status is not visible, just add an empty span
        spans.push(Span::styled(
            " ",
            Style::default().fg(Color::White),
        ));
    }

    // Right side: pattern name and mode
    spans.push(Span::styled(
        format!(" | Pattern: {} | Mode: {} | ←/→:select | Click:stamp",
            pattern_name,
            mode,
        ),
        Style::default().fg(Color::Cyan),
    ));

    Text::from(Line::from(spans))
}
