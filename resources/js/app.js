import "./bootstrap";

import videoFraming from "./components/video-framing.js";
import viewTranscript from "./components/view-transcript.js";

document.addEventListener("alpine:init", () => {
    window.Alpine.data("videoFraming", videoFraming);
    window.Alpine.data("viewTranscript", viewTranscript);
});
