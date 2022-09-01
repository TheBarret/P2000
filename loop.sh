
#!/bin/bash
until ./pipeline.php; do
    echo "Process crashed with exit code $?.  Respawning.." >&2
    sleep 1
done
