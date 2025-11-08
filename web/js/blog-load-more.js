(function () {
  const rawChunks = window.blogPostChunks;
  delete window.blogPostChunks;
  const chunks = Array.isArray(rawChunks) ? rawChunks.slice() : [];
  if (!chunks.length) {
    return;
  }

  const container = document.getElementById('blog-post-list');
  const loadMoreButton = document.getElementById('blog-load-more');

  if (!container || !loadMoreButton) {
    return;
  }

  let chunkIndex = 0;

  function createPostArticle(post) {
    const article = document.createElement('article');

    if (post.image) {
      const link = document.createElement('a');
      link.href = 'post.php?article=' + encodeURIComponent(post.slug);

      const picture = document.createElement('picture');

      if (post.imageWebp) {
        const webpSource = document.createElement('source');
        webpSource.type = 'image/webp';
        webpSource.srcset = post.imageWebp;
        picture.appendChild(webpSource);
      }

      if (post.imageJpg) {
        const jpgSource = document.createElement('source');
        jpgSource.type = 'image/jpeg';
        jpgSource.srcset = post.imageJpg;
        picture.appendChild(jpgSource);
      }

      const img = document.createElement('img');
      img.src = post.image;
      img.alt = post.title;
      img.className = 'post-list-thumb';
      picture.appendChild(img);

      link.appendChild(picture);
      article.appendChild(link);
    }

    const details = document.createElement('div');
    details.className = 'post-details';

    const h2 = document.createElement('h2');
    const titleLink = document.createElement('a');
    titleLink.href = 'post.php?article=' + encodeURIComponent(post.slug);
    titleLink.textContent = post.title;
    h2.appendChild(titleLink);
    details.appendChild(h2);

    const dateDiv = document.createElement('div');
    dateDiv.className = 'post-date';
    dateDiv.textContent = post.date;
    details.appendChild(dateDiv);

    if (Array.isArray(post.categories) && post.categories.length) {
      const catsDiv = document.createElement('div');
      catsDiv.className = 'post-cats';
      catsDiv.textContent = 'Categories: ' + post.categories.join(', ');
      details.appendChild(catsDiv);
    }

    const excerptPara = document.createElement('p');
    excerptPara.textContent = post.excerpt + ' ';

    const readMore = document.createElement('a');
    readMore.href = 'post.php?article=' + encodeURIComponent(post.slug);
    readMore.textContent = 'Read More';
    excerptPara.appendChild(readMore);

    details.appendChild(excerptPara);
    article.appendChild(details);

    return article;
  }

  function appendNextChunk() {
    if (chunkIndex >= chunks.length) {
      return;
    }

    const posts = chunks[chunkIndex];
    chunkIndex += 1;

    posts.forEach(function (post) {
      container.appendChild(createPostArticle(post));
    });

    if (chunkIndex >= chunks.length) {
      loadMoreButton.remove();
    }
  }

  loadMoreButton.addEventListener('click', appendNextChunk);
})();
