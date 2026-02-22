export default function viewTranscript({ segments, clips }) {
    return {
        segments,
        clips,
        gapThreshold: 2,
        highlightStart: null,
        highlightEnd: null,
        rows: [],
        highlightEnds: [],

        // Drag state
        dragging: null, // { clipIndex, edge: 'start'|'end' }
        dragPreviewStart: null,
        dragPreviewEnd: null,

        init() {
            this.recompute();
            this.$watch("gapThreshold", () => this.recompute());
        },

        recompute() {
            this.rows = [];
            this.highlightEnds = new Array(this.segments.length).fill(0);

            let previousEnd = null;

            for (let i = 0; i < this.segments.length; i++) {
                const segment = this.segments[i];

                if (previousEnd !== null) {
                    const gap = segment.start - previousEnd;
                    if (gap > this.gapThreshold) {
                        this.rows.push({
                            type: "gap",
                            label: this.formatGap(gap),
                            prevSegmentIndex: i - 1,
                            nextSegmentIndex: i,
                        });
                    }
                }

                this.rows.push({
                    type: "segment",
                    start: segment.start,
                    end: segment.end,
                    segmentIndex: i,
                    text: segment.text,
                });

                previousEnd = segment.end;
            }

            // Precompute highlight ends (60s window with clip avoidance).
            // Walk backward: since earlier segments start sooner, their
            // window ends at or before the next segment's window, so
            // shrinking from the previous answer gives O(n) total.
            const count = this.segments.length;

            // Find the first clip start index at or after each segment.
            // nextClipAt[i] = index of the first clip start >= i, or count if none.
            const nextClipAt = new Array(count).fill(count);
            for (const c of this.clips) {
                // Mark the clip start position
                if (c.start < count && nextClipAt[c.start] > c.start) {
                    nextClipAt[c.start] = c.start;
                }
            }
            // Fill backwards so nextClipAt[i] = min(nextClipAt[i], nextClipAt[i+1])
            for (let i = count - 2; i >= 0; i--) {
                if (nextClipAt[i] > nextClipAt[i + 1]) {
                    nextClipAt[i] = nextClipAt[i + 1];
                }
            }

            let lastHighlight = count - 1;

            for (let i = count - 1; i >= 0; i--) {
                const anchorStart = this.segments[i].start;

                if (i < count - 1) {
                    lastHighlight = this.highlightEnds[i + 1];
                }

                // Shrink back while the window exceeds 60s
                while (lastHighlight > i) {
                    if (this.segments[lastHighlight].end - anchorStart <= 60) {
                        break;
                    }
                    lastHighlight--;
                }

                // Don't highlight past a clip that starts after this segment
                if (
                    !this.inClip(i) &&
                    nextClipAt[i] > i &&
                    nextClipAt[i] <= lastHighlight
                ) {
                    lastHighlight = nextClipAt[i] - 1;
                }

                this.highlightEnds[i] = lastHighlight;
            }

            this.clearHighlight();
        },

        inClip(segmentIndex) {
            return this.clips.some((c, i) => {
                const start =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewStart
                        : c.start;
                const end =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewEnd
                        : c.end;
                return segmentIndex >= start && segmentIndex <= end;
            });
        },

        gapInClip(prevIndex, nextIndex) {
            return this.clips.some((c, i) => {
                const start =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewStart
                        : c.start;
                const end =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewEnd
                        : c.end;
                return prevIndex >= start && nextIndex <= end;
            });
        },

        clipIndexOfSegment(segmentIndex) {
            return this.clips.findIndex(
                (c) => segmentIndex >= c.start && segmentIndex <= c.end,
            );
        },

        isClipStart(segmentIndex) {
            return this.clips.some((c, i) => {
                const start =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewStart
                        : c.start;
                return segmentIndex === start;
            });
        },

        isClipEnd(segmentIndex) {
            return this.clips.some((c, i) => {
                const end =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewEnd
                        : c.end;
                return segmentIndex === end;
            });
        },

        clipDuration(clipIndex) {
            const c = this.clips[clipIndex];
            if (!c) return 0;
            const start =
                this.dragging && this.dragging.clipIndex === clipIndex
                    ? this.dragPreviewStart
                    : c.start;
            const end =
                this.dragging && this.dragging.clipIndex === clipIndex
                    ? this.dragPreviewEnd
                    : c.end;
            return this.segments[end].end - this.segments[start].start;
        },

        clipDurationOfSegment(segmentIndex) {
            const idx = this.clips.findIndex((c, i) => {
                const start =
                    this.dragging && this.dragging.clipIndex === i
                        ? this.dragPreviewStart
                        : c.start;
                return segmentIndex === start;
            });
            if (idx === -1) return 0;
            return this.clipDuration(idx);
        },

        formatDuration(seconds) {
            const total = Math.round(seconds);
            if (total >= 60) {
                const m = Math.floor(total / 60);
                const s = total % 60;
                return `${m}m ${String(s).padStart(2, "0")}s`;
            }
            return `${total}s`;
        },

        startDrag(segmentIndex, edge) {
            const clipIndex = this.clipIndexOfSegment(segmentIndex);
            if (clipIndex === -1) return;

            const c = this.clips[clipIndex];
            this.dragging = { clipIndex, edge };
            this.dragPreviewStart = c.start;
            this.dragPreviewEnd = c.end;
            this.clearHighlight();
        },

        handleDragOver(segmentIndex) {
            if (!this.dragging) return;

            const { clipIndex, edge } = this.dragging;
            const c = this.clips[clipIndex];
            let newStart =
                edge === "start" ? segmentIndex : this.dragPreviewStart;
            let newEnd = edge === "end" ? segmentIndex : this.dragPreviewEnd;

            // Can't drag start past end or vice versa
            if (newStart > newEnd) return;

            // Check 90s limit
            const duration =
                this.segments[newEnd].end - this.segments[newStart].start;
            if (duration > 90) return;

            // Check no overlap with other clips
            for (let i = 0; i < this.clips.length; i++) {
                if (i === clipIndex) continue;
                const other = this.clips[i];
                if (newStart <= other.end && newEnd >= other.start) return;
            }

            this.dragPreviewStart = newStart;
            this.dragPreviewEnd = newEnd;
        },

        async endDrag() {
            if (!this.dragging) return;

            const { clipIndex } = this.dragging;
            const c = this.clips[clipIndex];
            const newStart = this.dragPreviewStart;
            const newEnd = this.dragPreviewEnd;
            const changed = newStart !== c.start || newEnd !== c.end;

            // Reset drag state
            this.dragging = null;
            this.dragPreviewStart = null;
            this.dragPreviewEnd = null;

            if (!changed) return;

            // Optimistic update
            c.start = newStart;
            c.end = newEnd;
            this.recompute();

            // Persist and sync with server
            const clips = await this.$wire.updateClip(c.id, newStart, newEnd);
            this.clips = clips;
            this.recompute();
        },

        setHighlight(segmentIndex) {
            this.highlightStart = segmentIndex;
            this.highlightEnd = this.highlightEnds[segmentIndex];
        },

        clearHighlight() {
            this.highlightStart = null;
            this.highlightEnd = null;
        },

        isHighlighted(segmentIndex) {
            return (
                this.highlightStart !== null &&
                segmentIndex >= this.highlightStart &&
                segmentIndex <= this.highlightEnd
            );
        },

        isGapHighlighted(prevIndex, nextIndex) {
            return (
                this.highlightStart !== null &&
                prevIndex >= this.highlightStart &&
                nextIndex <= this.highlightEnd
            );
        },

        async createClip(startIndex, endIndex) {
            // Optimistic update
            this.clips.push({ id: null, start: startIndex, end: endIndex });
            this.recompute();

            // Persist and sync with server
            const clips = await this.$wire.createClip(startIndex, endIndex);
            this.clips = clips;
            this.recompute();
        },

        formatTimestamp(seconds) {
            const total = Math.floor(seconds);
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            const s = total % 60;

            const lastStart =
                this.segments.length > 0
                    ? this.segments[this.segments.length - 1].start
                    : 0;

            if (lastStart >= 3600) {
                return `${h}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
            }

            return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
        },

        formatGap(seconds) {
            const total = Math.round(seconds);

            if (total >= 60) {
                const m = Math.floor(total / 60);
                const s = total % 60;
                return `${m}m ${String(s).padStart(2, "0")}s pause`;
            }

            return `${total}s pause`;
        },
    };
}
