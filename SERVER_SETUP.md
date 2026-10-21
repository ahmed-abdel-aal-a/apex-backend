# Server Environment Setup

This project uses OS-level binaries to handle heavy processing (like extracting page counts from very large PDFs) without consuming PHP's memory limit. 

## Requirements
- **Poppler Utils (`pdfinfo`)**: Used to extract metadata (e.g., page counts) from PDF files instantly and with virtually zero memory overhead.

### Installation on Production (Linux / Ubuntu)

To install `poppler-utils` on your Ubuntu server, run the following commands as root or using `sudo`:

```bash
sudo apt-get update
sudo apt-get install -y poppler-utils
```

To verify the installation was successful, you can run:
```bash
pdfinfo -v
```

This ensures that the `PrintStationController` can safely use `Symfony\Component\Process\Process` to execute `pdfinfo` and process large PDF orders smoothly.
