export default function viewTranscript({ segments, clips }) {
    return {
        segments,
        clips,
        gapThreshold: 2,
        highlightStart: null,
        highlightEnd: null,
        rows: [],
        highlightEnds: [],

        // Drag state: null when idle, object when dragging
        dragging: null,

        init() {
            this.recompute();
            this.$watch("gapThreshold", () => this.recompute());

            // Global listeners for ending drags (user may release outside the transcript)
            this._endDragHandler = () => this.endDrag();
            this._escHandler = (e) => {
                if (e.key === "Escape" && this.dragging) {
                    this.dragging = null;
                    document.body.style.userSelect = "";
                }
            };
            document.addEventListener("pointerup", this._endDragHandler);
            document.addEventListener("pointercancel", this._endDragHandler);
            document.addEventListener("keydown", this._escHandler);
        },

        destroy() {
            document.removeEventListener("pointerup", this._endDragHandler);
            document.removeEventListener("pointercancel", this._endDragHandler);
            document.removeEventListener("keydown", this._escHandler);
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

                // Shrink back past any trailing clip segments so the
                // highlight never ends right before a gap into a clip
                while (lastHighlight > i && this.inStoredClip(lastHighlight)) {
                    lastHighlight--;
                }

                this.highlightEnds[i] = lastHighlight;
            }

            this.clearHighlight();
        },

        // --- Clip range helpers (drag-aware) ---

        effectiveClipRange(clip) {
            if (this.dragging && this.dragging.clipId === clip.id) {
                return {
                    start: this.dragging.currentStart,
                    end: this.dragging.currentEnd,
                };
            }
            return { start: clip.start, end: clip.end };
        },

        inStoredClip(segmentIndex) {
            return this.clips.some(
                (c) => segmentIndex >= c.start && segmentIndex <= c.end,
            );
        },

        inClip(segmentIndex) {
            return this.clips.some((c) => {
                const range = this.effectiveClipRange(c);
                return segmentIndex >= range.start && segmentIndex <= range.end;
            });
        },

        gapInClip(prevIndex, nextIndex) {
            return this.clips.some((c) => {
                const range = this.effectiveClipRange(c);
                return prevIndex >= range.start && nextIndex <= range.end;
            });
        },

        // --- Clip lookup helpers ---

        clipAt(segmentIndex) {
            return (
                this.clips.find((c) => {
                    const range = this.effectiveClipRange(c);
                    return (
                        segmentIndex >= range.start && segmentIndex <= range.end
                    );
                }) ?? null
            );
        },

        clipBounds(clipIndex) {
            const prevClip = clipIndex > 0 ? this.clips[clipIndex - 1] : null;
            const nextClip =
                clipIndex < this.clips.length - 1
                    ? this.clips[clipIndex + 1]
                    : null;

            return {
                minStart: prevClip ? prevClip.end + 1 : 0,
                maxEnd: nextClip
                    ? nextClip.start - 1
                    : this.segments.length - 1,
            };
        },

        clipHandleType(segmentIndex) {
            const clip = this.clipAt(segmentIndex);
            if (!clip) return null;
            const range = this.effectiveClipRange(clip);
            const isStart = segmentIndex === range.start;
            const isEnd = segmentIndex === range.end;
            if (isStart && isEnd) return "both";
            if (isStart) return "start";
            if (isEnd) return "end";
            return null;
        },

        clipFor(segmentIndex) {
            return this.clipAt(segmentIndex);
        },

        // --- Duration helpers ---

        clipDuration(startIndex, endIndex) {
            if (startIndex == null || endIndex == null) return 0;
            if (startIndex < 0 || endIndex >= this.segments.length) return 0;
            return (
                this.segments[endIndex].end - this.segments[startIndex].start
            );
        },

        formatDuration(seconds) {
            const total = Math.round(seconds);
            const m = Math.floor(total / 60);
            const s = total % 60;
            return `${m}:${String(s).padStart(2, "0")}`;
        },

        // --- Highlight and interaction ---

        setHighlight(segmentIndex) {
            if (this.dragging) return;
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

        // --- Drag lifecycle ---

        startDrag(clipId, handle, event) {
            event.preventDefault();
            event.stopPropagation();

            const clipIndex = this.clips.findIndex((c) => c.id === clipId);
            if (clipIndex === -1) return;
            const clip = this.clips[clipIndex];

            this.dragging = {
                clipId,
                clipIndex,
                handle,
                originalStart: clip.start,
                originalEnd: clip.end,
                currentStart: clip.start,
                currentEnd: clip.end,
            };

            document.body.style.userSelect = "none";
            this.clearHighlight();
        },

        dragOver(segmentIndex) {
            if (!this.dragging) return;

            const d = this.dragging;
            const bounds = this.clipBounds(d.clipIndex);
            let newStart = d.currentStart;
            let newEnd = d.currentEnd;

            if (d.handle === "start") {
                newStart = Math.max(
                    bounds.minStart,
                    Math.min(segmentIndex, d.currentEnd),
                );
            } else {
                newEnd = Math.min(
                    bounds.maxEnd,
                    Math.max(segmentIndex, d.currentStart),
                );
            }

            // Enforce 90-second max
            const duration = this.clipDuration(newStart, newEnd);
            if (duration > 90) return;

            d.currentStart = newStart;
            d.currentEnd = newEnd;
        },

        async endDrag() {
            if (!this.dragging) return;

            document.body.style.userSelect = "";

            const d = this.dragging;
            const changed =
                d.currentStart !== d.originalStart ||
                d.currentEnd !== d.originalEnd;

            if (changed) {
                // Optimistic update
                this.clips[d.clipIndex].start = d.currentStart;
                this.clips[d.clipIndex].end = d.currentEnd;

                const clipId = d.clipId;
                const newStart = d.currentStart;
                const newEnd = d.currentEnd;

                this.dragging = null;
                this.recompute();

                // Persist to server
                const clips = await this.$wire.updateClip(
                    clipId,
                    newStart,
                    newEnd,
                );
                this.clips = clips;
                this.recompute();
            } else {
                this.dragging = null;
            }
        },

        // --- Formatting ---

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
