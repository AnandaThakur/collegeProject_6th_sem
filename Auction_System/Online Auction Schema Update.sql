-- Add min_bid_increment column to auctions table if it doesn't exist
ALTER TABLE auctions ADD COLUMN IF NOT EXISTS min_bid_increment DECIMAL(10, 2) DEFAULT 1.00 AFTER reserve_price;

-- Add winner_id column to auctions table if it doesn't exist
ALTER TABLE auctions ADD COLUMN IF NOT EXISTS winner_id INT UNSIGNED NULL AFTER status;
ALTER TABLE auctions ADD CONSTRAINT IF NOT EXISTS fk_winner_id FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add winning_bid column to auctions table if it doesn't exist
ALTER TABLE auctions ADD COLUMN IF NOT EXISTS winning_bid DECIMAL(10, 2) NULL AFTER winner_id;

-- Add index on auction_id in bids table for faster queries
CREATE INDEX IF NOT EXISTS idx_auction_id ON bids(auction_id);

-- Add index on created_at in bids table for faster sorting
CREATE INDEX IF NOT EXISTS idx_created_at ON bids(created_at);

-- Add index on bid_amount in bids table for faster max queries
CREATE INDEX IF NOT EXISTS idx_bid_amount ON bids(bid_amount);
