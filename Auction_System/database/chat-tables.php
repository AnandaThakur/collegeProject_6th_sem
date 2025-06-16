-- Create auction_chat_messages table if it doesn't exist
CREATE TABLE IF NOT EXISTS auction_chat_messages (
    message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    message_content TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'deleted') DEFAULT 'active',
    FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (auction_id),
    INDEX (user_id),
    INDEX (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create chat_notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS chat_notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message_id INT UNSIGNED NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES auction_chat_messages(message_id) ON DELETE CASCADE,
    INDEX (user_id),
    INDEX (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
