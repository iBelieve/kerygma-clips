export default function calendarPage() {
    return {
        draggedClipName: null,
        draggedFromDate: null,
        hoveredDate: null,

        onDragStart(event, clipId, clipName, fromDate) {
            this.draggedClipName = clipName;
            this.draggedFromDate = fromDate || null;
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", String(clipId));
            event.target.classList.add("opacity-50");
        },

        onDragEnd(event) {
            event.target.classList.remove("opacity-50");
            this.draggedClipName = null;
            this.draggedFromDate = null;
            this.hoveredDate = null;
        },

        onDragOver(event, date) {
            event.preventDefault();
            if (date !== undefined) {
                this.hoveredDate = date;
            }
        },

        onDragLeave(event) {
            // Only clear if leaving the cell entirely (not entering a child element)
            if (!event.currentTarget.contains(event.relatedTarget)) {
                this.hoveredDate = null;
            }
        },

        async onDrop(event, date) {
            event.preventDefault();
            this.hoveredDate = null;
            this.draggedClipName = null;
            const clipId = parseInt(event.dataTransfer.getData("text/plain"));
            if (clipId) {
                await this.$wire.scheduleClip(clipId, date);
            }
        },

        async onDropToUnschedule(event) {
            event.preventDefault();
            this.hoveredDate = null;
            this.draggedClipName = null;
            const clipId = parseInt(event.dataTransfer.getData("text/plain"));
            if (clipId) {
                await this.$wire.unscheduleClip(clipId);
            }
        },

        async unschedule(clipId) {
            await this.$wire.unscheduleClip(clipId);
        },
    };
}
