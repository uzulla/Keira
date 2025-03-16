# Keira Systemd Service

This directory contains a sample systemd service configuration file for running Keira as a system service.

## Installation

1. Copy the service file to systemd's service directory:
   ```bash
   sudo cp keira.service /etc/systemd/system/
   ```

2. Create necessary directories:
   ```bash
   # Create configuration directory
   sudo mkdir -p /etc/keira
   
   # Create log and run directories if needed
   sudo mkdir -p /var/log/keira
   sudo mkdir -p /var/run/keira
   ```

3. Create a dedicated user for running the service (optional but recommended):
   ```bash
   sudo useradd -r -s /bin/false keira
   ```

4. Set proper permissions:
   ```bash
   sudo chown -R keira:keira /etc/keira
   sudo chown -R keira:keira /var/log/keira
   sudo chown -R keira:keira /var/run/keira
   ```

5. Copy your configuration file:
   ```bash
   sudo cp /path/to/your/config.json /etc/keira/config.json
   ```

6. Reload systemd to recognize the new service:
   ```bash
   sudo systemctl daemon-reload
   ```

## Usage

### Start the service:
```bash
sudo systemctl start keira
```

### Enable the service to start at boot:
```bash
sudo systemctl enable keira
```

### Check the status:
```bash
sudo systemctl status keira
```

### View logs:
```bash
sudo journalctl -u keira
```

### Tail logs in real-time:
```bash
sudo journalctl -u keira -f
```

### Reload configuration:
```bash
sudo systemctl reload keira
```

### Restart the service:
```bash
sudo systemctl restart keira
```

### Stop the service:
```bash
sudo systemctl stop keira
```

## Customization

You may need to adjust the following settings in the service file based on your specific requirements:

- **User/Group**: Change the user and group that the service runs as
- **WorkingDirectory**: Change to your Keira installation directory
- **ExecStart**: Update the path to PHP and the path to keira.php
- **Environment**: Set any environment variables needed (including KEIRA_CONFIG_PATH)
- **ReadWritePaths**: Adjust paths that need read/write access
- **Resource limits**: Adjust LimitNOFILE and LimitNPROC based on system capabilities

After making changes to the service file, reload systemd:
```bash
sudo systemctl daemon-reload
```

## Signals

The systemd service is configured to use signals to control Keira:

- **reload**: Sends SIGHUP to reload configuration
- **restart**: Stops and starts the service
- **stop**: Sends SIGTERM to gracefully stop the service

You can manually send signals using:
```bash
# Reload configuration
sudo systemctl reload keira

# Pause monitoring (SIGUSR1)
sudo kill -SIGUSR1 $(pidof keira)

# Resume monitoring (SIGUSR2)
sudo kill -SIGUSR2 $(pidof keira)
```