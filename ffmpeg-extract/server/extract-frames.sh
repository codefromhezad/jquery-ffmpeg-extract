#!/bin/bash



# Set Default Opts Values
readonly DEFAULT_NUM_EXTRACT_FRAMES=30
readonly DEFAULT_DIST_FOLDER="./dist"

readonly FFMPEG_PATH="/usr/local/bin/ffmpeg"
readonly FFPROBE_PATH="/usr/local/bin/ffprobe"

readonly LAUNCH_STATUS_ERROR="error"
readonly LAUNCH_STATUS_OK="ok"


# Check mandatory first argument is passed
if [[ $# -eq 0 ]] ; then
	echo 'Error: Not enough arguments.'
	echo
    echo 'Usage:'
    echo './extract-frames.sh <source-video-file> [<num_frames> <dest_folder>]'
    echo '	num_frames:  Number of frames to extract (default = '$DEFAULT_NUM_EXTRACT_FRAMES')'
    echo '	dest_folder: Destination folder path (default = "'$DEFAULT_DIST_FOLDER'")'
    exit 0
fi



# Retrieve the source file name
readonly SOURCE_FILENAME="$1"
readonly SOURCE_BASENAME=$(basename "$SOURCE_FILENAME" | tr "." "-")
readonly FFMPEG_OUTPUT_FILE="./tmp/ffmpeg-output.txt"
readonly FFMPEG_STATUS_FILE="./tmp/ffmpeg-status.txt"

readonly NUM_EXTRACT_FRAMES=${2:-$DEFAULT_NUM_EXTRACT_FRAMES}
readonly DIST_FOLDER=${3:-$DEFAULT_DIST_FOLDER}



# Get Video Duration in seconds and build string "num_frames/num_seconds" 
# to pass as an argument for the ffmpeg -fps option
float_vid_seconds=$($FFPROBE_PATH -i "$SOURCE_FILENAME" -show_format -v quiet | sed -n 's/duration=//p')
vid_seconds=${float_vid_seconds%.*}
vid_fps_string=$NUM_EXTRACT_FRAMES"/"$vid_seconds



# Create tmp dist folder
mkdir -p $DIST_FOLDER



# Empty the ffmpeg status file
> $FFMPEG_STATUS_FILE



# Actual extracting, redirect output to $FFMPEG_OUTPUT_FILE. Save the 
# number of jobs before and after launching the job.
jobs_number_before=$(jobs -p | wc -l)
$FFMPEG_PATH -i "$SOURCE_FILENAME" -vf fps=$vid_fps_string -qscale:v 2 -f image2 -c:v mjpeg $DIST_FOLDER/$SOURCE_BASENAME-frame-%03d.jpg 1> $FFMPEG_OUTPUT_FILE 2>&1 &
jobs_number_after=$(jobs -p | wc -l)



# Compare "before" and "after" jobs number. If it's the same, the job wasn't launched,
# which means we have an error.
if [ "$jobs_number_before" == "$jobs_number_after" ]; then
	echo "$LAUNCH_STATUS_ERROR" 1> $FFMPEG_STATUS_FILE 2>&1
else
	echo "$LAUNCH_STATUS_OK" 1> $FFMPEG_STATUS_FILE 2>&1
fi