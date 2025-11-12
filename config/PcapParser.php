<?php
/**
 * Pure PHP PCAP/PCAPNG Parser
 * Based on PCAP_PARSING_METHODOLOGY.md
 */

require_once __DIR__ . '/config.php';

class PcapParser {
    private $fileHandle;
    private $filePath;
    private $fileSize;
    private $byteOrder = 'little';
    private $format = 'pcap'; // 'pcap' or 'pcapng'
    private $sessions = [];
    private $streams = [];
    private $nextStreamId = 1;
    private $packetCount = 0;
    private $totalBytes = 0;
    private $firstTimestamp = null;
    private $lastTimestamp = null;
    
    // Magic numbers
    const PCAP_MAGIC_LE = "\xd4\xc3\xb2\xa1";
    const PCAP_MAGIC_BE = "\xa1\xb2\xc3\xd4";
    const PCAPNG_MAGIC = "\x0a\x0d\x0d\x0a";
    const BYTE_ORDER_MAGIC_LE = 0x4D3C2B1A;
    const BYTE_ORDER_MAGIC_BE = 0x1A2B3C4D;
    
    // Block types (PCAPNG)
    const BLOCK_TYPE_SHB = 0x0A0D0D0A;
    const BLOCK_TYPE_IDB = 0x00000001;
    const BLOCK_TYPE_EPB = 0x00000006;
    const BLOCK_TYPE_EOF = 0x00000000;
    
    // Protocol numbers
    const PROTO_ICMP = 1;
    const PROTO_TCP = 6;
    const PROTO_UDP = 17;
    
    // EtherTypes
    const ETHERTYPE_IPV4 = 0x0800;
    const ETHERTYPE_IPV6 = 0x86DD;
    
    /**
     * Parse a PCAP/PCAPNG file
     */
    public function parse($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        $this->filePath = $filePath;
        $this->fileSize = filesize($filePath);
        $this->fileHandle = fopen($filePath, 'rb');
        
        if (!$this->fileHandle) {
            throw new Exception("Cannot open file: $filePath");
        }
        
        try {
            // Detect file format
            $this->detectFormat();
            
            // Parse based on format
            if ($this->format === 'pcapng') {
                $this->parsePcapng();
            } else {
                $this->parsePcap();
            }
            
            // Finalize sessions
            $this->finalizeSessions();
            
            return $this->getResults();
        } finally {
            fclose($this->fileHandle);
        }
    }
    
    /**
     * Detect file format and byte order
     */
    private function detectFormat() {
        $magic = $this->readBytes(4);
        
        if ($magic === self::PCAPNG_MAGIC) {
            $this->format = 'pcapng';
            $this->detectPcapngByteOrder();
        } elseif ($magic === self::PCAP_MAGIC_LE) {
            $this->format = 'pcap';
            $this->byteOrder = 'little';
        } elseif ($magic === self::PCAP_MAGIC_BE) {
            $this->format = 'pcap';
            $this->byteOrder = 'big';
        } else {
            throw new Exception("Unknown file format. Magic: " . bin2hex($magic));
        }
    }
    
    /**
     * Detect byte order for PCAPNG
     */
    private function detectPcapngByteOrder() {
        $shbStartPos = ftell($this->fileHandle) - 4; // Position of block type (magic)
        
        // We already read the block type (magic), now read block length
        $blockLengthBytes = $this->readBytes(4);
        $byteOrderBytes = $this->readBytes(4);
        
        // Try both byte orders to read block length
        $blockLengthLE = unpack('V', $blockLengthBytes)[1];
        $blockLengthBE = unpack('N', $blockLengthBytes)[1];
        
        // Check byte-order magic in both interpretations
        $magicLE = unpack('V', $byteOrderBytes)[1];
        $magicBE = unpack('N', $byteOrderBytes)[1];
        
        if ($magicLE === self::BYTE_ORDER_MAGIC_LE || $magicBE === self::BYTE_ORDER_MAGIC_LE) {
            $this->byteOrder = 'little';
            $blockLength = $blockLengthLE;
        } elseif ($magicLE === self::BYTE_ORDER_MAGIC_BE || $magicBE === self::BYTE_ORDER_MAGIC_BE) {
            $this->byteOrder = 'big';
            $blockLength = $blockLengthBE;
        } else {
            throw new Exception("Invalid PCAPNG byte-order magic: " . dechex($magicLE) . " / " . dechex($magicBE));
        }
        
        // Skip to the end of the SHB block
        // Block length includes the 8-byte header (type + length)
        // We've read: 4 bytes (block type) + 4 bytes (block length) + 4 bytes (byte-order magic) = 12 bytes
        // So we need to skip: blockLength - 12 bytes to get to the duplicate length
        // Then skip 4 more bytes (the duplicate length itself) to get to the next block
        $remaining = $blockLength - 12;
        if ($remaining > 0 && $remaining < $this->fileSize) {
            $this->skipBytes($remaining);
        }
        
        // Read and verify duplicate block length
        $duplicateLength = $this->readUInt32();
        if ($duplicateLength !== $blockLength) {
            // Position correction: jump to expected end of SHB
            $expectedEnd = $shbStartPos + $blockLength;
            fseek($this->fileHandle, $expectedEnd);
        }
    }
    
    /**
     * Parse PCAP format
     */
    private function parsePcap() {
        // Skip global header (already read magic, skip rest of 24-byte header)
        $this->skipBytes(20);
        
        // Read packets
        while (!$this->isEof()) {
            $packet = $this->readPcapPacket();
            if ($packet === null) break;
            
            $this->processPacket($packet['data'], $packet['timestamp'], $packet['size']);
        }
    }
    
    /**
     * Read a single PCAP packet
     */
    private function readPcapPacket() {
        if ($this->isEof()) return null;
        
        // Packet header: 16 bytes
        // 0-3: timestamp seconds
        // 4-7: timestamp microseconds
        // 8-11: captured length
        // 12-15: original length
        
        $header = $this->readBytes(16);
        if (strlen($header) < 16) return null;
        
        $tsSec = $this->readUInt32(substr($header, 0, 4));
        $tsUsec = $this->readUInt32(substr($header, 4, 4));
        $capturedLen = $this->readUInt32(substr($header, 8, 4));
        $originalLen = $this->readUInt32(substr($header, 12, 4));
        
        // Calculate timestamp (seconds since epoch)
        $timestamp = $tsSec + ($tsUsec / 1000000.0);
        
        // Read packet data
        if ($capturedLen > 0) {
            $packetData = $this->readBytes($capturedLen);
        } else {
            $packetData = '';
        }
        
        return [
            'timestamp' => $timestamp,
            'data' => $packetData,
            'size' => $capturedLen
        ];
    }
    
    /**
     * Parse PCAPNG format
     */
    private function parsePcapng() {
        // Continue reading blocks (we already read the SHB)
        while (!$this->isEof()) {
            $packet = $this->readPcapngPacket();
            if ($packet === null) break;
            
            $this->processPacket($packet['data'], $packet['timestamp'], $packet['size']);
        }
    }
    
    /**
     * Read a single PCAPNG packet (Enhanced Packet Block)
     */
    private function readPcapngPacket() {
        while (!$this->isEof()) {
            // Read block header
            $blockTypeBytes = $this->readBytes(4);
            if (strlen($blockTypeBytes) < 4) return null;
            
            $blockLengthBytes = $this->readBytes(4);
            if (strlen($blockLengthBytes) < 4) return null;
            
            $blockType = $this->readUInt32($blockTypeBytes);
            $blockLength = $this->readUInt32($blockLengthBytes);
            
            // Validate block length
            if ($blockLength < 8 || $blockLength > $this->fileSize) {
                return null; // Invalid block
            }
            
            // Enhanced Packet Block (type 6)
            if ($blockType === 6 || $blockType === self::BLOCK_TYPE_EPB) {
                // Enhanced Packet Block - contains packet data
                return $this->parseEnhancedPacketBlock($blockLength);
            } elseif ($blockType === 0 || $blockType === self::BLOCK_TYPE_EOF) {
                // End of file
                return null;
            } else {
                // Other block types (SHB, IDB, etc.) - skip to end
                // Block length includes the 8-byte header, so skip (blockLength - 8) bytes
                $remaining = $blockLength - 8;
                if ($remaining > 0 && $remaining < $this->fileSize) {
                    $this->skipBytes($remaining);
                } else {
                    // Invalid block, try to continue
                    break;
                }
                continue;
            }
        }
        return null;
    }
    
    /**
     * Parse Enhanced Packet Block
     */
    private function parseEnhancedPacketBlock($blockLength) {
        $startPos = ftell($this->fileHandle) - 8; // Subtract 8 for block type + length we already read
        
        // Read fixed fields (20 bytes: interfaceId, timestampHigh, timestampLow, capturedLen, originalLen)
        $interfaceId = $this->readUInt32();
        $timestampHigh = $this->readUInt32();
        $timestampLow = $this->readUInt32();
        $capturedLen = $this->readUInt32();
        $originalLen = $this->readUInt32();
        
        // Read packet data
        $packetData = $this->readBytes($capturedLen);
        
        // Calculate padding (packet data must be 32-bit aligned)
        $padding = (4 - ($capturedLen % 4)) % 4;
        if ($padding > 0) {
            $this->skipBytes($padding);
        }
        
        // Read duplicate block length at end (4 bytes)
        $this->readUInt32();
        
        // Verify we're at the correct position (blockLength includes the 8-byte header)
        $currentPos = ftell($this->fileHandle);
        $expectedPos = $startPos + $blockLength;
        if ($currentPos !== $expectedPos) {
            // Adjust position if needed
            fseek($this->fileHandle, $expectedPos);
        }
        
        // Calculate timestamp (nanoseconds since 1970-01-01 00:00:00 UTC)
        $timestamp = ($timestampHigh * 4294967296.0 + $timestampLow) / 1000000000.0;
        
        return [
            'timestamp' => $timestamp,
            'data' => $packetData,
            'size' => $capturedLen
        ];
    }
    
    /**
     * Process a packet: parse layers and track sessions
     */
    private function processPacket($packetData, $timestamp, $size) {
        $this->packetCount++;
        $this->totalBytes += $size;
        
        // Track timestamps
        if ($this->firstTimestamp === null) {
            $this->firstTimestamp = $timestamp;
        }
        $this->lastTimestamp = $timestamp;
        
        // Parse packet layers
        $parsed = $this->parsePacketLayers($packetData);
        if ($parsed === null) return;
        
        // Track session
        $this->updateSession($parsed, $timestamp, $size);
    }
    
    /**
     * Parse packet layers (Ethernet -> IP -> TCP/UDP)
     */
    private function parsePacketLayers($packetData) {
        // Parse Ethernet frame
        if (strlen($packetData) < 14) return null;
        
        $ethHeader = substr($packetData, 0, 14);
        $etherType = unpack('n', substr($ethHeader, 12, 2))[1];
        $payload = substr($packetData, 14);
        
        // Parse IP layer
        if ($etherType === self::ETHERTYPE_IPV4 && strlen($payload) >= 20) {
            // IPv4
            $ipHeader = substr($payload, 0, 20);
            $ipHeaderLen = (ord($ipHeader[0]) & 0x0F) * 4;
            $protocol = ord($ipHeader[9]);
            $srcIP = inet_ntop(substr($ipHeader, 12, 4));
            $dstIP = inet_ntop(substr($ipHeader, 16, 4));
            $ipPayload = substr($payload, $ipHeaderLen);
            
            // Parse TCP
            if ($protocol === self::PROTO_TCP && strlen($ipPayload) >= 20) {
                $tcpHeader = substr($ipPayload, 0, 20);
                $srcPort = unpack('n', substr($tcpHeader, 0, 2))[1];
                $dstPort = unpack('n', substr($tcpHeader, 2, 2))[1];
                $flags = ord($tcpHeader[13]);
                
                return [
                    'source_ip' => $srcIP,
                    'destination_ip' => $dstIP,
                    'source_port' => $srcPort,
                    'destination_port' => $dstPort,
                    'protocol' => 'TCP',
                    'tcp_flags' => [
                        'syn' => ($flags & 0x02) !== 0,
                        'fin' => ($flags & 0x01) !== 0,
                        'ack' => ($flags & 0x10) !== 0,
                        'rst' => ($flags & 0x04) !== 0
                    ]
                ];
            }
            
            // Parse UDP
            if ($protocol === self::PROTO_UDP && strlen($ipPayload) >= 8) {
                $udpHeader = substr($ipPayload, 0, 8);
                $srcPort = unpack('n', substr($udpHeader, 0, 2))[1];
                $dstPort = unpack('n', substr($udpHeader, 2, 2))[1];
                
                return [
                    'source_ip' => $srcIP,
                    'destination_ip' => $dstIP,
                    'source_port' => $srcPort,
                    'destination_port' => $dstPort,
                    'protocol' => 'UDP'
                ];
            }
            
            // ICMP or other protocols
            return [
                'source_ip' => $srcIP,
                'destination_ip' => $dstIP,
                'source_port' => 0,
                'destination_port' => 0,
                'protocol' => $protocol === self::PROTO_ICMP ? 'ICMP' : 'OTHER'
            ];
        }
        
        return null;
    }
    
    /**
     * Update session tracking
     */
    private function updateSession($parsed, $timestamp, $size) {
        // Only track TCP and UDP sessions
        if ($parsed['protocol'] !== 'TCP' && $parsed['protocol'] !== 'UDP') {
            return;
        }
        
        // Get stream ID
        $streamId = $this->getStreamId(
            $parsed['source_ip'],
            $parsed['destination_ip'],
            $parsed['source_port'],
            $parsed['destination_port']
        );
        
        // Initialize session if new
        if (!isset($this->sessions[$streamId])) {
            $this->sessions[$streamId] = [
                'stream_id' => $streamId,
                'source_ip' => $parsed['source_ip'],
                'destination_ip' => $parsed['destination_ip'],
                'source_port' => $parsed['source_port'],
                'destination_port' => $parsed['destination_port'],
                'protocol' => $parsed['protocol'],
                'start_time' => $timestamp,
                'end_time' => $timestamp,
                'packet_count' => 0,
                'total_bytes' => 0,
                'closed' => false
            ];
        }
        
        // Update session
        $this->sessions[$streamId]['packet_count']++;
        $this->sessions[$streamId]['total_bytes'] += $size;
        $this->sessions[$streamId]['end_time'] = max($this->sessions[$streamId]['end_time'], $timestamp);
        
        // Check for session close (TCP only)
        if ($parsed['protocol'] === 'TCP' && isset($parsed['tcp_flags'])) {
            if ($parsed['tcp_flags']['fin'] || $parsed['tcp_flags']['rst']) {
                $this->sessions[$streamId]['closed'] = true;
            }
        }
    }
    
    /**
     * Get or create stream ID for a connection
     */
    private function getStreamId($srcIP, $dstIP, $srcPort, $dstPort) {
        // Normalize: always use smaller IP:port -> larger IP:port
        $key1 = "$srcIP:$srcPort-$dstIP:$dstPort";
        $key2 = "$dstIP:$dstPort-$srcIP:$srcPort";
        
        // Use existing stream ID if found
        if (isset($this->streams[$key1])) {
            return $this->streams[$key1];
        }
        if (isset($this->streams[$key2])) {
            return $this->streams[$key2];
        }
        
        // Create new stream ID
        $streamId = $this->nextStreamId++;
        $this->streams[$key1] = $streamId;
        $this->streams[$key2] = $streamId;
        return $streamId;
    }
    
    /**
     * Finalize sessions: calculate relative times and add geolocation
     */
    private function finalizeSessions() {
        $baseTime = $this->firstTimestamp;
        
        foreach ($this->sessions as &$session) {
            // Calculate relative times (seconds from start)
            $session['relative_start'] = $session['start_time'] - $baseTime;
            $session['relative_end'] = $session['end_time'] - $baseTime;
            
            // Determine external IP (not private)
            $externalIP = $this->getExternalIP($session['source_ip'], $session['destination_ip']);
            $session['external_ip'] = $externalIP;
            
            // Get geolocation for external IP
            if ($externalIP) {
                $geo = $this->getIPGeolocation($externalIP);
                if ($geo) {
                    $session['geolocation'] = $geo;
                }
            }
        }
    }
    
    /**
     * Get external IP (non-private)
     */
    private function getExternalIP($ip1, $ip2) {
        if (!$this->isPrivateIP($ip1)) {
            return $ip1;
        }
        if (!$this->isPrivateIP($ip2)) {
            return $ip2;
        }
        return null;
    }
    
    /**
     * Check if IP is private
     */
    private function isPrivateIP($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return false;
    }
    
    /**
     * Get IP geolocation (cached)
     */
    private function getIPGeolocation($ip) {
        static $cache = [];
        
        if (isset($cache[$ip])) {
            return $cache[$ip];
        }
        
        // Skip private IPs
        if ($this->isPrivateIP($ip)) {
            return null;
        }
        
        // Use ip-api.com (free, no key required)
        $url = GEO_API_URL . $ip . '?fields=status,country,countryCode,lat,lon,city,isp';
        $context = stream_context_create([
            'http' => [
                'timeout' => GEO_API_TIMEOUT,
                'user_agent' => 'PCAP Parser/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['status']) && $data['status'] === 'success') {
                $geo = [
                    'country' => $data['country'] ?? '',
                    'country_code' => $data['countryCode'] ?? '',
                    'latitude' => floatval($data['lat'] ?? 0),
                    'longitude' => floatval($data['lon'] ?? 0),
                    'city' => $data['city'] ?? '',
                    'isp' => $data['isp'] ?? ''
                ];
                $cache[$ip] = $geo;
                return $geo;
            }
        }
        
        return null;
    }
    
    /**
     * Get parsing results
     */
    private function getResults() {
        return [
            'format' => $this->format,
            'total_packets' => $this->packetCount,
            'total_bytes' => $this->totalBytes,
            'capture_start_time' => $this->firstTimestamp,
            'capture_duration' => $this->lastTimestamp - $this->firstTimestamp,
            'total_sessions' => count($this->sessions),
            'sessions' => array_values($this->sessions),
            'processed_at' => time()
        ];
    }
    
    // Helper methods for binary reading
    
    private function readBytes($length) {
        $data = fread($this->fileHandle, $length);
        return $data === false ? '' : $data;
    }
    
    private function readUInt32($data = null) {
        if ($data === null) {
            $data = $this->readBytes(4);
        }
        if (strlen($data) < 4) return 0;
        $unpacked = unpack($this->byteOrder === 'little' ? 'V' : 'N', $data);
        return $unpacked[1];
    }
    
    private function readUInt16($data = null) {
        if ($data === null) {
            $data = $this->readBytes(2);
        }
        if (strlen($data) < 2) return 0;
        $unpacked = unpack($this->byteOrder === 'little' ? 'v' : 'n', $data);
        return $unpacked[1];
    }
    
    private function skipBytes($length) {
        fseek($this->fileHandle, $length, SEEK_CUR);
    }
    
    private function isEof() {
        return feof($this->fileHandle) || ftell($this->fileHandle) >= $this->fileSize;
    }
}

