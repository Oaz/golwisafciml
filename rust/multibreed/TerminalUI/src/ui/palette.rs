//! Breed-to-color palette mapping per Appendix B
//!
//! Maps breed values (0-15) to RGB color values for terminal rendering.

use ratatui::style::Color;
use gol_multibreed_core::Breed;

/// Returns the Ratatui Color for a given breed
/// Uses exact RGB values from Appendix B
pub fn breed_to_color(breed: Breed) -> Color {
    let rgb = breed_to_rgb(breed);
    Color::Rgb(rgb.0, rgb.1, rgb.2)
}

/// Returns RGB tuple (r, g, b) for a breed per Appendix B
/// 
/// Appendix B breed color palette:
/// - 0: RGB(127, 127, 127) - Gray
/// - 1: RGB(127,   0,   0) - Dark Red
/// - 2: RGB(  0, 127,   0) - Dark Green
/// - 3: RGB(  0,   0, 127) - Dark Blue
/// - 4: RGB(255,   0,   0) - Red
/// - 5: RGB(255, 255,   0) - Yellow
/// - 6: RGB( 63, 190, 255) - Light Blue
/// - 7: RGB( 63, 255, 190) - Light Cyan
/// - 8: RGB(  0, 255,   0) - Green
/// - 9: RGB(190,  63, 255) - Light Magenta
/// - 10: RGB(  0, 255, 255) - Cyan
/// - 11: RGB(190, 255,  63) - Light Yellow
/// - 12: RGB(  0,   0, 255) - Blue
/// - 13: RGB(255,  63, 190) - Light Pink
/// - 14: RGB(255, 190,  63) - Light Orange
/// - 15: RGB(255,   0, 255) - Magenta
fn breed_to_rgb(breed: Breed) -> (u8, u8, u8) {
    match breed.value() {
        0 => (127, 127, 127),
        1 => (127, 0, 0),
        2 => (0, 127, 0),
        3 => (0, 0, 127),
        4 => (255, 0, 0),
        5 => (255, 255, 0),
        6 => (63, 190, 255),
        7 => (63, 255, 190),
        8 => (0, 255, 0),
        9 => (190, 63, 255),
        10 => (0, 255, 255),
        11 => (190, 255, 63),
        12 => (0, 0, 255),
        13 => (255, 63, 190),
        14 => (255, 190, 63),
        15 => (255, 0, 255),
        _ => (127, 127, 127), // unreachable for valid Breed values
    }
}

/// Returns a colored span for a breed (for breed selector display)
pub fn breed_color_span(breed: Breed) -> ratatui::text::Span<'static> {
    use ratatui::style::Style;
    let color = breed_to_color(breed);
    ratatui::text::Span::styled(
        " █ ",
        Style::default().fg(color),
    )
}
