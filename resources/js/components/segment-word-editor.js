export default function segmentWordEditor() {
    return {
        segmentIndex: null,
        words: [],
        editedTexts: [],
        saving: false,

        get hasChanges() {
            return this.words.some((w, i) => w.word !== this.editedTexts[i]);
        },

        get hasEmptyWords() {
            return this.editedTexts.some((t) => t.trim() === "");
        },

        get canSave() {
            return this.hasChanges && !this.hasEmptyWords && !this.saving;
        },

        openModal(segmentIndex, words) {
            this.segmentIndex = segmentIndex;
            this.words = words.map((w) => ({ ...w }));
            this.editedTexts = words.map((w) => w.word);
            this.$dispatch("open-modal", { id: "edit-segment-words" });
        },

        closeModal() {
            this.$dispatch("close-modal", { id: "edit-segment-words" });
            this.segmentIndex = null;
            this.words = [];
            this.editedTexts = [];
        },

        async save() {
            if (!this.canSave) return;

            this.saving = true;

            try {
                await this.$wire.updateSegmentWords(
                    this.segmentIndex,
                    this.editedTexts,
                );
                this.closeModal();
            } finally {
                this.saving = false;
            }
        },

        inputSize(text) {
            return Math.max(text.length + 1, 3);
        },
    };
}
