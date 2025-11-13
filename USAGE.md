# 📖 Usage Guide

Complete guide on how to use PCAP Network Analyzer.

## Table of Contents

- [Getting Started](#getting-started)
- [Uploading PCAP Files](#uploading-pcap-files)
- [Playing Back Network Traffic](#playing-back-network-traffic)
- [Configuring HQ Location](#configuring-hq-location)
- [Managing Files](#managing-files)
- [Understanding the Interface](#understanding-the-interface)
- [Tips & Best Practices](#tips--best-practices)

## Getting Started

### First Time Setup

1. **Access the Dashboard**
   - Open your browser and navigate to the dashboard URL
   - You should see an empty map with playback controls

2. **Upload Your First PCAP File**
   - Click on "Upload" in the navigation or go to `/upload.php`
   - Select a PCAP or PCAPNG file from your computer
   - Wait for the upload to complete

3. **Process the File**
   - Click the "▶ Process" button next to your uploaded file
   - Wait for processing to complete (status will change from PENDING → PROCESSING → PROCESSED)
   - Processing time depends on file size (large files may take several minutes)

4. **Load and Play**
   - Click "Load & Play" or select the file from the dropdown in the dashboard header
   - The map will update with network sessions
   - Use playback controls to navigate through time

## Uploading PCAP Files

### Supported Formats

- **PCAP** (`.pcap`) - Traditional libpcap format
- **PCAPNG** (`.pcapng`) - Next-generation capture format (recommended)

### Upload Process

1. **Navigate to Upload Page**
   - Click "Upload" link in the navigation
   - Or go directly to `/upload.php`

2. **Select File**
   - Drag and drop a file into the upload area, OR
   - Click "Browse" to select a file from your computer
   - Maximum file size depends on your PHP configuration (default: 2MB, can be increased)

3. **Upload Status**
   - **PENDING**: File uploaded successfully, ready to process
   - **PROCESSING**: File is being parsed (please wait)
   - **PROCESSED**: File is ready to use
   - **ERROR**: Processing failed (click retry to try again)

4. **Process the File**
   - Click "▶ Process" button for PENDING files
   - For ERROR files, click "🔄 Retry" to reprocess
   - Processing extracts:
     - Network sessions (TCP/UDP connections)
     - Packet timestamps
     - IP addresses and ports
     - Geolocation data for external IPs

### File Requirements

- Valid PCAP/PCAPNG format
- Contains TCP or UDP traffic (ICMP-only captures won't show sessions)
- File size within PHP upload limits

## Playing Back Network Traffic

### Playback Controls

The dashboard provides several controls for navigating through network traffic:

| Control | Action | Description |
|---------|--------|-------------|
| ⏮⏮ | First Frame | Jump to the beginning of the capture |
| ⏮ | Previous Step | Step backward by one time interval |
| ▶ / ⏸ | Play/Pause | Start or pause automatic playback |
| ⏭ | Next Step | Step forward by one time interval |
| ⏭⏭ | Last Frame | Jump to the end of the capture |
| 🔁 | Loop | Enable/disable continuous playback |

### Step Navigation

- **Step Display**: Shows current step number and total steps (e.g., "5 / 100")
- **Jump to Step**: Enter a step number in the input field and click "Jump" to go directly to that step
- **Adaptive Steps**: Step size automatically adjusts based on capture duration:
  - Short captures (< 1s): Millisecond precision (0.001s steps)
  - Medium captures (1-10s): Centisecond precision (0.01s or 0.1s steps)
  - Long captures (> 10s): Second precision (1.0s steps)

### Understanding the Timeline

- **Current Time**: Shows the actual timestamp within the capture
- **Step Count**: Indicates your position in the playback (e.g., "Step 5 of 100")
- **Duration**: Total capture duration in seconds

### Visual Indicators

- **Green Pulsing Markers**: New sessions that appeared in the current step
- **Blue Markers**: Existing active sessions
- **HQ Marker**: Your configured headquarters location (red marker)

## Configuring HQ Location

### Setting HQ Location

1. **Open Settings**
   - Click the "⚙️ Settings" button in the dashboard header
   - A modal dialog will appear

2. **Enter Coordinates**
   - **Latitude**: Decimal degrees (e.g., 24.7136)
   - **Longitude**: Decimal degrees (e.g., 46.6753)
   - **Name**: Display name for the location (e.g., "Riyadh, KSA")

3. **Save**
   - Click "Save" to update the HQ location
   - The map will automatically center on the new location
   - A red marker will appear at the HQ location

### Finding Coordinates

- Use Google Maps: Right-click on a location → "What's here?" → Copy coordinates
- Use online tools like [LatLong.net](https://www.latlong.net/)
- Use GPS coordinates from your device

## Managing Files

### Switching Between Files

1. **Using the Dropdown**
   - Click the PCAP file dropdown in the dashboard header
   - Select a different processed file
   - Playback will automatically reset to step 1

2. **From Upload Page**
   - Go to the upload page
   - Click "Load & Play" next to any processed file

### Deleting Files

1. **Navigate to Upload Page**
2. **Find the file** you want to delete
3. **Click the delete button** (🗑️)
4. **Confirm deletion**
   - Both the original PCAP file and processed JSON will be deleted
   - This action cannot be undone

### Reprocessing Files

If a file failed to process or you want to reprocess it:

1. Go to the upload page
2. Find the file with ERROR status
3. Click "🔄 Retry" to reprocess
4. Wait for processing to complete

## Understanding the Interface

### Dashboard Layout

```
┌─────────────────────────────────────────────────────────┐
│  Header: PCAP Selector | Stats | Settings | Upload      │
├─────────────────────────────────────────────────────────┤
│  Playback Controls: [⏮⏮] [⏮] [▶] [⏭] [⏭⏭] [🔁]      │
│  Step: 5 / 100 | Time: 2024-01-15 10:30:45             │
│  Jump to Step: [____] [Jump]                            │
├─────────────────────────────────────────────────────────┤
│                                                          │
│              Interactive World Map                       │
│         (Network sessions as markers)                    │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Active Sessions Table                                  │
│  ┌──────┬──────────┬──────────┬───────┬────────┐      │
│  │ IP   │ Location │ Protocol │ Port  │ Packets│      │
│  └──────┴──────────┴──────────┴───────┴────────┘      │
└─────────────────────────────────────────────────────────┘
```

### Session Information

Click on any marker on the map to see:

- **IP Address**: External IP address of the session
- **Location**: City and country (from geolocation)
- **Protocol**: TCP or UDP
- **Port**: Destination port number
- **Packets**: Number of packets in the session
- **Bytes**: Total bytes transferred

### Sessions Table

The table below the map shows all active sessions at the current timestamp:

- **IP Address**: External IP
- **Location**: City, Country
- **Protocol**: TCP/UDP
- **Port**: Destination port
- **Packets**: Packet count
- **Bytes**: Total bytes
- **Duration**: Session duration
- **Date/Time**: Timestamp

## Tips & Best Practices

### For Best Results

1. **Use PCAPNG Format**: More metadata and better support for modern captures
2. **Capture Duration**: Shorter captures (< 1 minute) provide better granularity
3. **Traffic Types**: Focus on TCP/UDP traffic for session visualization
4. **File Size**: Keep files under 100MB for faster processing

### Performance Tips

- **Large Files**: Processing may take several minutes for files > 50MB
- **Browser**: Use modern browsers (Chrome, Firefox, Edge) for best performance
- **Internet**: Ensure stable connection for geolocation API calls

### Troubleshooting

**No Sessions Appearing?**
- Verify the PCAP contains TCP/UDP traffic
- Check that external IPs have geolocation data
- Ensure file processing completed successfully

**Playback Not Working?**
- Verify a PCAP file is loaded (check dropdown)
- Check browser console for errors
- Ensure API endpoints are accessible

**Slow Performance?**
- Reduce file size or capture duration
- Close other browser tabs
- Check internet connection for geolocation

### Advanced Usage

- **Step Jumping**: Use step numbers to quickly navigate to specific moments
- **Loop Playback**: Enable loop for continuous analysis
- **Multiple Files**: Compare different captures by switching files
- **HQ Location**: Set HQ to see relative positions of network traffic

---

**Need Help?** Check the [README.md](README.md) for detailed documentation or open an issue on [GitHub](https://github.com/rfvillacacan/agila-v2/issues).

