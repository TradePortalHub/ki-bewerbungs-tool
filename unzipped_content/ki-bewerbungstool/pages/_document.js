// FILE: pages/_document.js
import Document, { Html, Head, Main, NextScript } from 'next/document';

class MyDocument extends Document {
  render() {
    return (
      <Html lang="de">
        <Head />
        <body>
          <Main />

          {/*  >>> WICHTIG: Route-Announcer-Element <<< */}
          <p
            id="__next-route-announcer__"
            role="alert"
            aria-live="assertive"
            style={{
              position: 'absolute',
              width: '1px',
              height: '1px',
              margin: '-1px',
              overflow: 'hidden',
              clip: 'rect(0 0 0 0)',
              whiteSpace: 'nowrap',
              border: 0,
            }}
          />

          <NextScript />
        </body>
      </Html>
    );
  }
}

export default MyDocument;
