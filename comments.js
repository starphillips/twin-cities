document.addEventListener("DOMContentLoaded", function () {
    const cityID = document.getElementById("city-id").value; // Hidden input with city ID
    const commentForm = document.getElementById("comment-form");
    const commentList = document.getElementById("comment-list");
    const searchInput = document.getElementById("comment-search");

    function fetchComments(search = '') {
        fetch(`comments.php?cityID=${cityID}&search=${encodeURIComponent(search)}`)
            .then(response => response.json())
            .then(data => {
                commentList.innerHTML = "";
                data.forEach(comment => {
                    const commentItem = document.createElement("div");
                    commentItem.classList.add("comment");
                    commentItem.innerHTML = `<strong>${comment.username}</strong>: ${comment.comment} <em>(${comment.created_at})</em>`;
                    commentList.appendChild(commentItem);
                });
            });
    }

    commentForm.addEventListener("submit", function (e) {
        e.preventDefault();
        const username = document.getElementById("username").value;
        const comment = document.getElementById("comment").value;

        fetch("comments.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `cityID=${cityID}&username=${encodeURIComponent(username)}&comment=${encodeURIComponent(comment)}`
        }).then(() => {
            commentForm.reset();
            fetchComments();
        });
    });

    searchInput.addEventListener("input", function () {
        fetchComments(searchInput.value);
    });

    fetchComments(); // Load comments on page load
});
