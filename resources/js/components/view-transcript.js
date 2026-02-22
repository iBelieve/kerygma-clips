export default function viewTranscript({ segments, clips }) {
    return {
        segments,
        clips,
        gapThreshold: 2,
        highlightStart: null,
        highlightEnd: null,
        rows: [],
        highlightEnds: [],

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
                while (lastHighlight > i && this.inClip(lastHighlight)) {
                    lastHighlight--;
                }

                this.highlightEnds[i] = lastHighlight;
            }

            this.clearHighlight();
        },

        inClip(segmentIndex) {
            return this.clips.some(
                (c) => segmentIndex >= c.start && segmentIndex <= c.end,
            );
        },

        gapInClip(prevIndex, nextIndex) {
            return this.clips.some(
                (c) => prevIndex >= c.start && nextIndex <= c.end,
            );
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
