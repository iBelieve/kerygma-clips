export default function calendarPage() {
  return {
    onDragStart(event, clipId) {
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", String(clipId));
      event.target.classList.add("opacity-50");
    },

    onDragEnd(event) {
      event.target.classList.remove("opacity-50");
    },

    onDragOver(event) {
      event.preventDefault();
      event.currentTarget.classList.add(
        "!bg-amber-50",
        "dark:!bg-amber-900/20",
      );
    },

    onDragLeave(event) {
      event.currentTarget.classList.remove(
        "!bg-amber-50",
        "dark:!bg-amber-900/20",
      );
    },

    async onDrop(event, date) {
      event.preventDefault();
      event.currentTarget.classList.remove(
        "!bg-amber-50",
        "dark:!bg-amber-900/20",
      );
      const clipId = parseInt(event.dataTransfer.getData("text/plain"));
      if (clipId) {
        await this.$wire.scheduleClip(clipId, date);
      }
    },

    async onDropToUnschedule(event) {
      event.preventDefault();
      event.currentTarget.classList.remove(
        "!bg-amber-50",
        "dark:!bg-amber-900/20",
      );
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
