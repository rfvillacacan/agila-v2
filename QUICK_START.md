# Quick Start Guide

Get up and running with PCAP Network Analyzer in minutes!

## Prerequisites

- PHP 7.4+ installed
- Web server (Apache/Nginx) or PHP built-in server
- Write permissions for `uploads/` directory

## Installation (5 minutes)

### Option 1: Using PHP Built-in Server (Development)

1. **Navigate to project directory**:
   ```bash
   cd agila-v2
   ```

2. **Start PHP server**:
   ```bash
   php -S localhost:8000
   ```

3. **Open browser**:
   - Dashboard: http://localhost:8000/
   - Upload: http://localhost:8000/upload.php

### Option 2: Using XAMPP/WAMP/MAMP

1. **Copy project** to web server directory:
   - XAMPP: `C:\xampp\htdocs\agila-v2`
   - WAMP: `C:\wamp64\www\agila-v2`
   - MAMP: `/Applications/MAMP/htdocs/agila-v2`

2. **Set permissions** (Linux/Mac):
   ```bash
   chmod 755 uploads
   chmod 755 uploads/pcap
   chmod 755 uploads/processed
   ```

3. **Access application**:
   - Dashboard: http://localhost/agila-v2/
   - Upload: http://localhost/agila-v2/upload.php

## First Steps

1. **Upload a PCAP file**:
   - Go to Upload page
   - Drag & drop or browse for a `.pcap` or `.pcapng` file
   - Click "▶ Process" button
   - Wait for processing to complete

2. **View on Dashboard**:
   - Click "Load & Play" or select file from dropdown
   - Use playback controls to navigate through time
   - Click markers to see session details

3. **Configure HQ Location** (Optional):
   - Click Settings button
   - Enter coordinates and name
   - Save to see HQ marker on map

## Sample PCAP Files

You can create sample PCAP files using:

- **Wireshark**: Capture live traffic or open existing captures
- **tcpdump**: `tcpdump -w capture.pcap`
- **Sample captures**: Download from [Wireshark Sample Captures](https://wiki.wireshark.org/SampleCaptures)

## Troubleshooting

### "Upload failed" error
- Check PHP `upload_max_filesize` in `php.ini`
- Ensure `uploads/` directory is writable

### "Processing stuck"
- Check PHP `max_execution_time` in `php.ini`
- Check PHP error logs

### "No sessions on map"
- Verify PCAP contains TCP/UDP traffic
- Check browser console for errors

## Next Steps

- Read the full [README.md](README.md) for detailed documentation
- Check [API Documentation](README.md#api-documentation) for integration
- Explore [Architecture](README.md#architecture) for understanding the codebase

---

**Need help?** Open an issue on GitHub!

