// const url = 'Scripts/PHP/VideoUpload.php';
// const form = document.querySelector('form');
//
// form.addEventListener('submit', e => {
//     e.preventDefault();
//
//     const files = document.querySelector('[type=file]').files;
//     const formData = new FormData();
//
//     for (let i = 0; i < files.length; i++) {
//         let file = files[i];
//
//         formData.append('files[]', file);
//     }
//
//     fetch(url, {
//         method: 'POST',
//         body: formData
//     }).then(response => {
//         console.log(response);
//     });
// });