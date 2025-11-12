# Setup Instructions

## Quick Start

1. **Copy the project** to your web server directory:
   ```
   C:\xampp\htdocs\agila-v2\
   ```

2. **Set directory permissions** (if needed):
   - Ensure `uploads/` directory is writable
   - PHP needs write access to create processed files

3. **Access the application**:
   - Dashboard: `http://localhost/agila-v2/`
   - Upload: `http://localhost/agila-v2/upload.php`

## Verification

After setup, verify:

1. ✅ Dashboard loads without errors
2. ✅ Upload page is accessible
3. ✅ Can upload a PCAP file
4. ✅ File processes successfully
5. ✅ Sessions appear on map during playback

## Troubleshooting

### PHP Errors
- Check `php.ini` for `upload_max_filesize` and `post_max_size`
- Ensure `max_execution_time` is sufficient for large files

### Permission Errors
- Ensure `uploads/` directory has write permissions
- Check PHP error logs

### No Sessions on Map
- Verify PCAP file contains TCP/UDP traffic
- Check browser console for JavaScript errors
- Verify API endpoints are accessible

## Configuration

Edit `config/config.php` to customize:
- Default HQ location
- File size limits
- Processing timeout
- Geolocation API settings

