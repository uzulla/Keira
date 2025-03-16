# Keira - PHP Web Server Monitor

Keira is a high-performance web server monitoring application built with PHP 8.3 and amphp. It allows you to monitor multiple web servers in real-time with minimal resource usage.

## Features

- High-frequency monitoring of multiple servers (targeting 100 servers every 500ms)
- Real-time alerts via Slack when thresholds are exceeded
- 24-hour data retention for response time history
- REST API for monitoring status
- WebSocket for real-time updates
- Signal handling for configuration reloading and monitoring control

## Requirements

- PHP 8.3+
- amphp libraries

## Installation

```bash
# Clone the repository
git clone https://github.com/uzulla/Keira.git
cd Keira

# Install dependencies
composer install
```

## Configuration

Create a configuration file in JSON format:

```json
{
  "slack": {
    "webhook_url": "https://hooks.slack.com/services/T00000000/B0000000/XXXXXXXXXXXXXXXXXXXXXXXX",
    "channel": "#alerts-channel"
  },
  "monitors": [
    {
      "id": "service-api-1",
      "url": "https://example.com/api/health",
      "interval_ms": 500,
      "timeout_ms": 1000,
      "expected_status": 200,
      "expected_content": "OK",
      "alert_threshold": 3,
      "ignore_tls_error": true,
      "is_active": true
    }
  ]
}
```

## Usage

```bash
# Start the monitor with a configuration file
php bin/keira.php --config=/path/to/config.json
```

## Signal Handling

- `SIGHUP`: Reload configuration (preserves monitoring data)
- `SIGUSR1`: Pause monitoring (preserves data)
- `SIGUSR2`: Resume monitoring

## API Endpoints

- `GET /monitors`: List all monitors
- `GET /monitor/{id}`: Get status of a specific monitor
- WebSocket `/realtime/`: Real-time monitoring updates

## License

MIT
