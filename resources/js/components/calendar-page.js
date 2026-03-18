export default function calendarPage() {
    return {
        hoveredDate: null,
        dragging: false,

        onDragStart(event, clipId) {
            this.dragging = true;
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", String(clipId));
            event.target.classList.add("opacity-50");
        },

        onDragEnd(event) {
            event.target.classList.remove("opacity-50");
            this.hoveredDate = null;
            this.dragging = false;
        },

        onClipClick(event, clipId) {
            if (this.dragging) return;
            window.open(`/clips/${clipId}/edit`, "_blank");
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
            const clipId = parseInt(event.dataTransfer.getData("text/plain"));
            if (clipId) {
                await this.$wire.scheduleClip(clipId, date);
            }
        },

        async onDropToUnschedule(event) {
            event.preventDefault();
            this.hoveredDate = null;
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
