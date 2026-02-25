export default function readonlyTranscript({ segments }) {
    return {
        segments,
        gapThreshold: 2,
        rows: [],

        init() {
            this.recompute();
            this.$watch("gapThreshold", () => this.recompute());
        },

        recompute() {
            this.rows = [];

            let previousEnd = null;

            for (let i = 0; i < this.segments.length; i++) {
                const segment = this.segments[i];

                if (previousEnd !== null) {
                    const gap = segment.start - previousEnd;
                    if (gap > this.gapThreshold) {
                        this.rows.push({
                            type: "gap",
                            label: this.formatGap(gap),
                        });
                    }
                }

                this.rows.push({
                    type: "segment",
                    start: segment.start,
                    text: segment.text,
                });

                previousEnd = segment.end;
            }
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
