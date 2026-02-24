export default function videoFraming({ cropCenter, videoId }) {
    return {
        cropCenter,
        originalCropCenter: cropCenter,
        sourceWidth: 0,
        sourceHeight: 0,
        dragging: false,
        dragStartX: 0,
        dragStartCenter: 0,
        saving: false,

        get cropRatio() {
            if (!this.sourceWidth || !this.sourceHeight) return 0.3;
            return (
                Math.min((this.sourceHeight * 9) / 16, this.sourceWidth) /
                this.sourceWidth
            );
        },

        get boxWidthPct() {
            return this.cropRatio * 100;
        },

        get boxLeftPct() {
            const halfBox = this.boxWidthPct / 2;
            return Math.max(
                0,
                Math.min(this.cropCenter - halfBox, 100 - this.boxWidthPct),
            );
        },

        get changed() {
            return this.cropCenter !== this.originalCropCenter;
        },

        init() {
            const img = this.$refs.frame;
            const onLoad = () => {
                this.sourceWidth = img.naturalWidth;
                this.sourceHeight = img.naturalHeight;
            };
            if (img.complete && img.naturalWidth) onLoad();
            else img.addEventListener("load", onLoad);
        },

        startDrag(e) {
            e.preventDefault();
            this.dragging = true;
            this.dragStartX = e.clientX;
            this.dragStartCenter = this.cropCenter;
        },

        onDrag(e) {
            if (!this.dragging) return;
            const container = this.$refs.container;
            const dx = e.clientX - this.dragStartX;
            const dPct = (dx / container.offsetWidth) * 100;
            const halfBox = this.boxWidthPct / 2;
            this.cropCenter = Math.round(
                Math.max(
                    halfBox,
                    Math.min(this.dragStartCenter + dPct, 100 - halfBox),
                ),
            );
        },

        endDrag() {
            this.dragging = false;
        },

        async save() {
            this.saving = true;
            await this.$wire.updateVideoFraming(videoId, this.cropCenter);
            this.originalCropCenter = this.cropCenter;
            this.saving = false;
        },
    };
}
