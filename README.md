# 🌐 PCAP Network Analyzer

<div align="center">

**A powerful web-based network traffic analyzer that parses PCAP/PCAPNG files and visualizes network sessions on an interactive world map with time-based playback.**

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active-success.svg)](https://github.com)

*Conceptualized & Developed by [Rolly Falco Villacacan](https://github.com)*

</div>

---

## 📋 Table of Contents

- [Features](#-features)
- [Screenshots](#-screenshots)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Usage](#-usage)
- [Architecture](#-architecture)
- [API Documentation](#-api-documentation)
- [Technical Details](#-technical-details)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Features

### Core Capabilities

- 🔍 **Pure PHP PCAP/PCAPNG Parser** - No external dependencies, parses both PCAP and PCAPNG formats natively
- 🗺️ **Interactive World Map** - Visualize network sessions with real-time geolocation data using Leaflet.js
- ▶️ **Time-based Playback** - Play, pause, step through network traffic over time with precise control
- 🔗 **Session Tracking** - Automatically groups packets into TCP/UDP sessions with bidirectional flow tracking
- 📍 **HQ Location Configuration** - Set and display headquarters location on the map
- 📁 **File Management** - Upload, delete, and reprocess PCAP files with status tracking
- ⚡ **Asynchronous Processing** - Background processing with real-time status updates
- 🎯 **Smart Step Calculation** - Adaptive step sizing based on file size and capture duration
- 🔄 **Loop Playback** - Continuous playback with automatic restart from beginning
- 🎨 **Visual Indicators** - Animated markers for new sessions with pulsing effects

### Advanced Features

- **Multi-file Support** - Switch between multiple uploaded PCAP files seamlessly
- **Step Navigation** - Jump to specific steps in the playback timeline
- **Auto-zoom** - Automatically fit map to show all active sessions
- **Session Details** - View detailed information for each network session in popups
- **Geolocation Integration** - Automatic IP geolocation using ip-api.com
- **Status Tracking** - Real-time processing status (Pending, Processing, Processed, Error)

## 🖼️ Screenshots

*Screenshots coming soon - Add your dashboard screenshots here*

## 📦 Requirements

- **PHP**: 7.4 or higher
- **Web Server**: Apache or Nginx
- **Permissions**: Write permissions for `uploads/` directory
- **Internet Connection**: Required for geolocation API (ip-api.com)

### PHP Extensions

- `json` - For JSON encoding/decoding
- `mbstring` - For string manipulation (usually enabled by default)

## 🚀 Installation

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/pcap-network-analyzer.git
   cd pcap-network-analyzer/agila-v2
   ```

2. **Set up directory permissions** (Linux/Mac)
   ```bash
   chmod 755 uploads
   chmod 755 uploads/pcap
   chmod 755 uploads/processed
   ```
   
   **Windows**: Ensure the `uploads` folder has write permissions for the web server user.

3. **Start the application**

   **Option A: PHP Built-in Server (Recommended for Development)**
   ```bash
   php -S localhost:8000
   ```
   Then open: http://localhost:8000/

   **Option B: XAMPP/WAMP/MAMP**
   - Copy the `agila-v2` folder to your web server directory:
     - XAMPP: `C:\xampp\htdocs\agila-v2`
     - WAMP: `C:\wamp64\www\agila-v2`
     - MAMP: `/Applications/MAMP/htdocs/agila-v2`
   - Access via: http://localhost/agila-v2/

   **Option C: Apache/Nginx (Production)**
   
   **Apache**: Ensure mod_rewrite is enabled (if using .htaccess)
   
   **Nginx**: Add to your server configuration:
   ```nginx
   location /agila-v2 {
       root /path/to/your/webroot;
       index index.php;
       try_files $uri $uri/ /agila-v2/index.php?$query_string;
   }
   ```

4. **Verify installation**
   - Dashboard: `http://localhost/agila-v2/` or `http://localhost:8000/`
   - Upload Page: `http://localhost/agila-v2/upload.php` or `http://localhost:8000/upload.php`

### Docker Installation (Optional)

*Docker configuration coming soon*

## 📖 Usage

> **📘 For detailed usage instructions, see [USAGE.md](USAGE.md)**

### Uploading PCAP Files

1. Navigate to the **Upload** page (`upload.php`)
2. **Drag and drop** a PCAP/PCAPNG file or click to browse
3. Wait for upload to complete (status: **PENDING**)
4. Click **"▶ Process"** to start processing
5. Wait for processing to complete (status: **PROCESSING** → **PROCESSED**)
6. Click **"Load & Play"** to start analyzing on the dashboard

### Playing Back Network Traffic

1. **Select a PCAP file** from the dropdown in the header
2. Use playback controls:
   - **⏮⏮ First Frame**: Jump to the beginning
   - **⏮ Previous Step**: Step back in time
   - **▶ Play**: Start time-based playback
   - **⏸ Pause**: Pause playback
   - **⏭ Next Step**: Step forward in time
   - **⏭⏭ Last Frame**: Jump to the end
   - **🔁 Loop**: Enable/disable continuous playback
3. **Watch sessions** appear and disappear on the map based on their timestamps
4. **Click markers** to view detailed session information
5. **Jump to step**: Enter a step number and click the jump button

### Configuring HQ Location

1. Click the **"⚙️ Settings"** button
2. Enter **latitude** and **longitude** coordinates
3. Enter a **name** for the HQ location
4. Click **"Save"** to update the map

### Managing PCAP Files

- **Switch Files**: Use the dropdown in the header to select different PCAP files
- **Delete Files**: Go to the upload page and click the delete button for any file
- **Reprocess Files**: Click the retry button to reprocess a file if needed

## 🏗️ Architecture

### Project Structure

```
agila-v2/
├── api/                          # REST API endpoints
│   ├── upload_pcap.php          # Handle file uploads
│   ├── get_pcap_files.php       # List all PCAP files
│   ├── set_playback.php         # Set active PCAP file
│   ├── get_playback_data.php    # Get sessions for current time
│   ├── playback_control.php     # Playback controls (play/pause/next/prev)
│   ├── hq_location.php          # HQ location management
│   ├── delete_pcap.php          # Delete PCAP files
│   └── reprocess_pcap.php       # Reprocess PCAP files
├── assets/
│   ├── css/
│   │   └── dashboard.css        # Dashboard styles
│   └── js/
│       ├── dashboard.js         # Dashboard logic & map
│       └── upload.js            # Upload page logic
├── config/
│   ├── config.php               # Application configuration
│   └── PcapParser.php           # Pure PHP PCAP/PCAPNG parser
├── uploads/                      # User data (gitignored)
│   ├── pcap/                    # Original PCAP files
│   ├── processed/               # Processed JSON files
│   ├── hq_location.json         # HQ location settings
│   └── playback_state.json      # Current playback state
├── index.php                     # Main dashboard
├── upload.php                    # Upload interface
├── README.md                     # This file
└── LICENSE                       # MIT License
```

### Data Flow

1. **Upload**: User uploads PCAP file → Stored in `uploads/pcap/`
2. **Process**: Parser reads PCAP → Extracts sessions → Adds geolocation → Saves JSON to `uploads/processed/`
3. **Playback**: Dashboard loads JSON → Filters sessions by time → Displays on map
4. **Control**: User controls playback → API updates state → Dashboard refreshes

## 📡 API Documentation

### Endpoints

#### `POST /api/upload_pcap.php`
Upload a PCAP file.

**Request**: `multipart/form-data` with `pcap_file`

**Response**:
```json
{
  "success": true,
  "filename": "pcap_1234567890.json",
  "status": "pending",
  "message": "File uploaded successfully. Click 'Process' to start processing."
}
```

#### `GET /api/get_pcap_files.php`
List all uploaded PCAP files.

**Response**:
```json
{
  "success": true,
  "files": [
    {
      "filename": "pcap_1234567890.json",
      "original_filename": "sample.pcapng",
      "status": "processed",
      "total_packets": 12345,
      "total_bytes": 12345678,
      "uploaded_at": 1234567890,
      "processed_at": 1234567891
    }
  ]
}
```

#### `POST /api/set_playback.php`
Set the active PCAP file for playback.

**Request**:
```json
{
  "filename": "pcap_1234567890.json"
}
```

#### `GET /api/get_playback_data.php`
Get active sessions for the current playback time.

**Response**:
```json
{
  "success": true,
  "current_time": 123.45,
  "total_duration": 3600.0,
  "actual_duration": 3600.0,
  "sessions": [...],
  "total_sessions": 100,
  "active_sessions": 25,
  "pcap_file": "pcap_1234567890.json",
  "pcap_info": {...}
}
```

#### `POST /api/playback_control.php`
Control playback state.

**Request**:
```json
{
  "action": "play" | "pause" | "next" | "previous" | "first" | "last" | "advance_time" | "set_time",
  "time": 123.45  // Optional, for "set_time" action
}
```

#### `GET/POST /api/hq_location.php`
Get or set HQ location.

**GET Response**:
```json
{
  "success": true,
  "lat": 24.7136,
  "lng": 46.6753,
  "name": "Riyadh, KSA"
}
```

**POST Request**:
```json
{
  "lat": 24.7136,
  "lng": 46.6753,
  "name": "Riyadh, KSA"
}
```

#### `POST /api/delete_pcap.php`
Delete a PCAP file and its processed data.

**Request**:
```json
{
  "filename": "pcap_1234567890.json"
}
```

#### `POST /api/reprocess_pcap.php`
Reprocess a PCAP file.

**Request**:
```json
{
  "filename": "pcap_1234567890.json"
}
```

## 🔧 Technical Details

### PCAP Parsing

The parser implements a pure PHP solution for parsing PCAP and PCAPNG files:

- **Format Detection**: Automatically detects PCAP vs PCAPNG based on magic numbers
- **Byte Order Detection**: Handles both little-endian and big-endian files
- **Block Parsing**: Correctly parses PCAPNG block structure (SHB, IDB, EPB)
- **Layer Parsing**: Extracts Ethernet, IP (IPv4/IPv6), TCP/UDP headers
- **Session Tracking**: Groups packets into bidirectional sessions using 5-tuple (src IP, dst IP, src port, dst port, protocol)

### Data Structure

Processed PCAP files are stored as JSON:

```json
{
  "status": "processed",
  "format": "pcapng",
  "original_filename": "sample.pcapng",
  "total_packets": 12345,
  "total_bytes": 12345678,
  "capture_start_time": 1234567890.123,
  "capture_duration": 3600.0,
  "total_sessions": 100,
  "sessions": [
    {
      "stream_id": 1,
      "source_ip": "192.168.1.1",
      "destination_ip": "8.8.8.8",
      "source_port": 12345,
      "destination_port": 53,
      "protocol": "UDP",
      "start_time": 1234567890.123,
      "end_time": 1234567890.456,
      "relative_start": 0.0,
      "relative_end": 0.333,
      "packet_count": 5,
      "total_bytes": 500,
      "external_ip": "8.8.8.8",
      "geolocation": {
        "country": "United States",
        "country_code": "US",
        "latitude": 37.4056,
        "longitude": -122.0775,
        "city": "Mountain View",
        "isp": "Google LLC"
      }
    }
  ]
}
```

### Step Calculation Algorithm

The application uses adaptive step sizing based on file size and capture duration:

- **Files < 1 second**: 0.001s steps (millisecond precision)
- **Files < 1 second, >10MB**: Minimum 200 steps
- **Files < 1 second, >50MB**: Minimum 500 steps
- **Files 1-10 seconds**: 0.1s steps
- **Files > 10 seconds**: 1s steps

This ensures large files always have sufficient granularity for navigation.

### Performance Considerations

- **Background Processing**: Large files are processed asynchronously to avoid timeouts
- **Efficient Parsing**: Stream-based parsing minimizes memory usage
- **Caching**: Processed JSON files are cached to avoid re-parsing
- **Lazy Loading**: Map markers are created only for active sessions

## 🐛 Troubleshooting

### Files Not Processing

- Check PHP `upload_max_filesize` and `post_max_size` settings in `php.ini`
- Ensure `uploads/` directory has write permissions (755 or 775)
- Check PHP error logs for detailed error messages
- Verify file is a valid PCAP/PCAPNG format

### No Sessions Appearing on Map

- Verify the PCAP file contains TCP/UDP traffic (ICMP-only captures won't show sessions)
- Check that external IPs have valid geolocation data (private IPs are filtered)
- Ensure sessions have valid timestamps
- Check browser console for JavaScript errors

### Playback Not Working

- Verify a PCAP file is loaded (check dropdown in header)
- Check browser console for JavaScript errors
- Ensure API endpoints are accessible (check Network tab)
- Verify `playback_state.json` is writable

### Geolocation Not Working

- Check internet connection (requires access to ip-api.com)
- Verify API is not rate-limited (free tier: 45 requests/minute)
- Check browser console for API errors

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Add comments for complex logic
- Test your changes with various PCAP files
- Update documentation as needed

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Conceptualized & Developed by**: [Rolly Falco Villacacan](https://github.com)
- **PCAP Parsing**: Based on libpcap and PCAPNG specifications
- **Map Visualization**: [Leaflet.js](https://leafletjs.com/)
- **Geolocation API**: [ip-api.com](http://ip-api.com/) (free tier)

## 📞 Support

For issues, questions, or suggestions, please open an issue on GitHub.

---

<div align="center">

**Made with ❤️ for network analysis**

⭐ Star this repo if you find it useful!

</div>
