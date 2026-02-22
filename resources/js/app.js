import "./bootstrap";

import viewTranscript from "./components/view-transcript.js";

document.addEventListener("alpine:init", () => {
    window.Alpine.data("viewTranscript", viewTranscript);
});
