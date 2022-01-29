import { Component, State, Prop, h } from '@stencil/core';
import '@webpress/core';
import { Connection, Post, Query } from '@webpress/core';

// printed to head in functions/inc/popular-posts-api
declare const BT_AB_PP: {
  posts: Array<{ id: number; weight: number }>;
};

@Component({
  tag: 'ab-popular-posts',
  styleUrl: 'ab-popular-posts.css',
})
export class AnalyticsBridgePopularPosts {
  @Prop() global: Connection.Context;
  @Prop() renderPost: (post: Post, weight: number) => any;
  @Prop() size;

  private connection;
  private posts = new Array(...BT_AB_PP.posts);
  private query: Query<Post>;

  @State() loadedPosts: Post[];

  render() {
    if (!this.loadedPosts) {
      return 'loading...';
    }
    return this.posts.map((popPost, index) => {
      if (index >= this.size) {
        return;
      }
      let post = this.loadedPosts.find(post => popPost.id == post.id);
      if (this.renderPost) {
        return this.renderPost(post, popPost.weight);
      } else {
        return <wp-title post={post} permalink={true} />;
      }
    });
  }

  componentDidLoad() {
    if (!this.global) {
      return;
    }

    this.connection = new Connection(this.global.serverInfo);

    if (this.query) {
      return;
    }

    let ids = this.posts.reduce((arr, popPost) => {
      arr.push(popPost.id);
      return arr;
    }, new Array<number>());

    this.query = new Query(
      this.connection,
      Post.QueryArgs({
        include: [...ids].splice(0, this.size || this.posts.length),
        per_page: this.size || this.posts.length,
      }),
    );

    this.query.results.then(posts => {
      this.loadedPosts = posts;
    });
  }
}
