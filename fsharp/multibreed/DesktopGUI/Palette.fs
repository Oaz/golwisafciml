namespace GameOfLife.Multibreed.DesktopGUI

open GameOfLife.Multibreed.Core
open Canvas
open Color

/// Module for breed-to-color palette mapping per Appendix B
module Palette =
    /// Maps a Breed to its RGB color per Appendix B
    /// Uses fromRgb to create colors from RGB tuples
    let breedToColor (breed: Breed) : color =
        let b = Breed.value breed
        match b with
        | 0 -> fromRgb 127 127 127   // Gray
        | 1 -> fromRgb 127 0 0       // Dark Red
        | 2 -> fromRgb 0 127 0       // Dark Green
        | 3 -> fromRgb 0 0 127       // Dark Blue
        | 4 -> fromRgb 255 0 0       // Red
        | 5 -> fromRgb 255 255 0     // Yellow
        | 6 -> fromRgb 63 190 255    // Light Blue
        | 7 -> fromRgb 63 255 190    // Light Cyan
        | 8 -> fromRgb 0 255 0       // Green
        | 9 -> fromRgb 190 63 255   // Purple
        | 10 -> fromRgb 0 255 255    // Cyan
        | 11 -> fromRgb 190 255 63   // Light Green
        | 12 -> fromRgb 0 0 255      // Blue
        | 13 -> fromRgb 255 63 190   // Pink
        | 14 -> fromRgb 255 190 63   // Orange
        | 15 -> fromRgb 255 0 255    // Magenta
        | _ -> fromRgb 127 127 127  // Default to gray (should never happen due to Breed masking)
