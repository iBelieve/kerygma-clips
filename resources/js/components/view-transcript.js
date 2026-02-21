export default function viewTranscript() {
    return {
        highlightStart: null,
        highlightEnd: null,

        clearHighlight() {
            this.highlightStart = null;
            this.highlightEnd = null;
        },

        setHighlight(start, end) {
            this.highlightStart = start;
            this.highlightEnd = end;
        },

        isHighlighted(index) {
            return (
                this.highlightStart !== null &&
                index >= this.highlightStart &&
                index <= this.highlightEnd
            );
        },

        isGapHighlighted(prevSegmentIndex, nextSegmentIndex) {
            return (
                this.highlightStart !== null &&
                prevSegmentIndex >= this.highlightStart &&
                nextSegmentIndex <= this.highlightEnd
            );
        },
    };
}
