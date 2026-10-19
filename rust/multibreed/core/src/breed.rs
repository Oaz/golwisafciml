/// A breed value in range 0-15, rendered as a color.
/// Uses u8 with smart constructor to ensure valid range.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub struct Breed(u8);

impl Breed {
    /// Creates a new Breed from a u8 value, masking to 4 bits (0-15).
    /// This ensures the breed is always in the valid range.
    pub const fn new(value: u8) -> Self {
        Self(value & 0b0000_1111)
    }

    /// Returns the underlying u8 value (0-15).
    pub const fn value(&self) -> u8 {
        self.0
    }

    /// All possible breed values.
    pub const fn all() -> [Self; 16] {
        [
            Self(0),
            Self(1),
            Self(2),
            Self(3),
            Self(4),
            Self(5),
            Self(6),
            Self(7),
            Self(8),
            Self(9),
            Self(10),
            Self(11),
            Self(12),
            Self(13),
            Self(14),
            Self(15),
        ]
    }
}

impl From<u8> for Breed {
    fn from(value: u8) -> Self {
        Self::new(value)
    }
}

impl From<Breed> for u8 {
    fn from(breed: Breed) -> Self {
        breed.value()
    }
}
