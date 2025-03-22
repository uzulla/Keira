# Keira - High-Performance Web Server Monitor

Keira is a high-performance web server monitoring application built with PHP 8.3 and amphp. It allows you to monitor multiple servers at high frequency (e.g., 100 servers every 500ms) and provides real-time alerts when issues are detected.

## Features

- **High-frequency monitoring**: Monitor hundreds of servers with configurable intervals
- **Real-time alerts**: Get instant Slack notifications when servers experience issues
- **WebSocket support**: Real-time updates via WebSocket
- **RESTful API**: Access monitoring data via a simple API
- **Signal handling**: Control the application with UNIX signals
- **Configurable thresholds**: Set custom alert thresholds for each server
- **TLS error handling**: Option to ignore TLS certificate errors
- **24-hour data retention**: Automatically cleans up old monitoring data

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
# Start the monitor with a configuration file (command line argument)
php bin/keira.php --config=/path/to/config.json

# Alternatively, use an environment variable to specify the config path
export KEIRA_CONFIG_PATH=/path/to/config.json
php bin/keira.php
```

### with Docker

```bash
# Build the Docker image
docker build -t keira .
```

### Running with Docker

```bash
# Run Keira
docker run keira

# Run Keira and if you want to kill with C-c
docker run -it keira

# Run Keira with a your configuration file.
docker run -v /path/to/your/config.json:/keira/config.json keira

# Expose API and WebSocket ports
docker run -v /path/to/your/config.json:/keira/config.json -p 8080:8080 -p 8081:8081 keira
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
