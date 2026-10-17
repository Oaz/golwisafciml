//! Crossterm-based EventSource implementation

use anyhow::{Context, Result};
use ::crossterm::event::{self, Event as CrosstermEvent, KeyEvent, MouseEvent};
use crossterm::terminal::{disable_raw_mode, enable_raw_mode, EnterAlternateScreen, LeaveAlternateScreen};
use crossterm::event::EnableMouseCapture;
use crossterm::ExecutableCommand;
use std::io::{stdout, Write};
use std::sync::mpsc::{self, Receiver, Sender};
use std::thread;
use std::time::Duration;

use super::Event;

/// EventSource that uses crossterm for event handling
pub struct EventSource {
    receiver: Receiver<Event>,
    sender: Sender<Event>,
}

impl EventSource {
    /// Creates a new EventSource with crossterm
    pub fn new() -> Result<Self> {
        // Enable raw mode
        enable_raw_mode().context("Failed to enable raw mode")?;

        // Enter alternate screen
        stdout()
            .execute(EnterAlternateScreen)
            .context("Failed to enter alternate screen")?;
        
        // Enable mouse capture
        stdout()
            .execute(EnableMouseCapture)
            .context("Failed to enable mouse capture")?;

        let (sender, receiver) = mpsc::channel();

        // Start event listener thread
        let sender_clone = sender.clone();
        thread::spawn(move || {
            let mut last_tick = std::time::Instant::now();
            let tick_interval = Duration::from_millis(100);

            loop {
                // Check if it's time for a tick
                if last_tick.elapsed() >= tick_interval {
                    if sender_clone.send(Event::Tick).is_err() {
                        break;
                    }
                    last_tick = std::time::Instant::now();
                }

                // Try to read event with timeout
                if event::poll(Duration::from_millis(10)).unwrap_or(false) {
                    match event::read().unwrap() {
                        CrosstermEvent::Key(key) => {
                            if sender_clone.send(Event::Key(key)).is_err() {
                                break;
                            }
                        }
                        CrosstermEvent::Mouse(mouse) => {
                            if sender_clone.send(Event::Mouse(mouse)).is_err() {
                                break;
                            }
                        }
                        CrosstermEvent::Resize(width, height) => {
                            if sender_clone.send(Event::Resize(width, height)).is_err() {
                                break;
                            }
                        }
                        _ => {}
                    }
                }
            }
        });

        Ok(Self { receiver, sender })
    }

    /// Reads the next event
    pub fn next_event(&self) -> Result<Event> {
        self.receiver
            .recv()
            .map_err(|e| anyhow::anyhow!("Event channel closed: {}", e))
    }

    /// Cleanly shuts down the event source and restores terminal state
    pub fn cleanup(&self) -> Result<()> {
        use crossterm::event::DisableMouseCapture;
        
        // Send quit event to stop the thread
        drop(self.sender.clone());

        // Restore terminal
        disable_raw_mode().context("Failed to disable raw mode")?;
        stdout()
            .execute(LeaveAlternateScreen)
            .context("Failed to leave alternate screen")?;
        stdout()
            .execute(DisableMouseCapture)
            .context("Failed to disable mouse capture")?;
        stdout().flush().context("Failed to flush stdout")?;

        Ok(())
    }
}

impl Drop for EventSource {
    fn drop(&mut self) {
        let _ = self.cleanup();
    }
}

/// Converts crossterm KeyEvent to our Event type
impl From<KeyEvent> for Event {
    fn from(key: KeyEvent) -> Self {
        Event::Key(key)
    }
}

/// Converts crossterm MouseEvent to our Event type
impl From<MouseEvent> for Event {
    fn from(mouse: MouseEvent) -> Self {
        Event::Mouse(mouse)
    }
}
