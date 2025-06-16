#!/bin/bash
# Script to set up proper permissions for the uploads directory on Mac

# Get the current directory
CURRENT_DIR=$(pwd)
echo "Current directory: $CURRENT_DIR"

# Create the uploads directory structure if it doesn't exist
echo "Creating uploads directory structure..."
mkdir -p uploads/auctions

# Set permissions
echo "Setting permissions..."
chmod -R 777 uploads

# Set ownership to web server user (on Mac it's typically _www)
echo "Setting ownership to web server user..."
chown -R _www:_www uploads

echo "Done! The uploads directory should now have the correct permissions."
echo "If you're still experiencing issues, please run this script with sudo:"
echo "sudo ./setup-mac-permissions.sh"
