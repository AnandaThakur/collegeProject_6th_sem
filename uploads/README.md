# Uploads Directory

This directory is used to store uploaded files for the auction system.

## Structure

- `/uploads/auctions/` - Contains auction images organized by auction ID
- Each auction has its own directory: `/uploads/auctions/{auction_id}/`

## Troubleshooting

### Permission Issues on Mac

If you're experiencing permission issues on Mac, try the following:

1. Open Terminal
2. Navigate to your project directory:
   \`\`\`
   cd /path/to/your/project
   \`\`\`
3. Create the directories manually:
   \`\`\`
   mkdir -p uploads/auctions
   \`\`\`
4. Set permissions:
   \`\`\`
   chmod -R 777 uploads
   \`\`\`
5. Make sure the web server user has access:
   \`\`\`
   chown -R _www:_www uploads
   \`\`\`
   (For macOS, the web server user is typically '_www')

### Common Issues

1. **"Failed to create directory"**: This usually means the web server doesn't have write permissions to the parent directory.

2. **"Failed to move uploaded file"**: This can happen if:
   - The temporary file doesn't exist
   - The destination directory doesn't exist
   - The web server doesn't have permission to write to the destination

3. **"Destination directory is not writable"**: The web server user needs write permissions to the directory.

## Manual Upload Alternative

If you continue to experience issues with file uploads, you can:

1. Create the auction without images
2. Manually upload images to the server in the correct directory
3. Update the database with the image paths

## Contact Support

If you continue to experience issues, please contact the system administrator.
